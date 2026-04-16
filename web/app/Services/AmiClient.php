<?php

namespace App\Services;

use RuntimeException;

class AmiClient
{
    /** @var resource|null */
    private $socket = null;
    private string $buffer = '';

    public function __construct(
        private string $host,
        private int $port,
        private string $username,
        private string $password
    ) {
    }

    public function connect(): void
    {
        $this->socket = @stream_socket_client(
            "tcp://{$this->host}:{$this->port}",
            $errno,
            $error,
            10,
            STREAM_CLIENT_CONNECT
        );

        if (!$this->socket) {
            throw new RuntimeException("AMI connection failed: {$error}");
        }

        // Read (and discard) the AMI banner line before sending commands.
        stream_set_blocking($this->socket, true);
        fgets($this->socket);

        stream_set_blocking($this->socket, false);
        $this->sendAction([
            'Action' => 'Login',
            'Username' => $this->username,
            'Secret' => $this->password,
            'Events' => 'on',
        ]);

        // Wait up to 5 s for the login response and verify it succeeded.
        $deadline = microtime(true) + 5;
        $loginBuf = '';
        while (microtime(true) < $deadline) {
            $read = [$this->socket];
            $w = $e = null;
            if (@stream_select($read, $w, $e, 0, 200000) > 0) {
                $chunk = fread($this->socket, 4096);
                if ($chunk === false || $chunk === '') {
                    fclose($this->socket);
                    $this->socket = null;
                    throw new RuntimeException('AMI connection closed before login response.');
                }
                $loginBuf .= $chunk;
                if (str_contains($loginBuf, "\r\n\r\n")) {
                    break;
                }
            }
        }

        if (!str_contains($loginBuf, 'Response: Success')) {
            fclose($this->socket);
            $this->socket = null;
            throw new RuntimeException('AMI authentication failed. Check AMI username/password in .env and manager.conf.');
        }
    }

    public function close(): void
    {
        if ($this->socket) {
            try {
                $this->sendAction(['Action' => 'Logoff']);
            } catch (\Throwable) {
            }
            fclose($this->socket);
            $this->socket = null;
        }
    }

    public function reconnect(): void
    {
        $this->close();
        $this->connect();
    }

    public function isConnected(): bool
    {
        return $this->socket !== null;
    }

    public function originate(
        string $actionId,
        string $channel,
        string $context,
        string $extension,
        int $priority,
        array $variables,
        int $timeoutMs = 30000
    ): void {
        $pairs = [];
        foreach ($variables as $key => $value) {
            if ($value !== null && $value !== '') {
                $pairs[] = $key . '=' . $value;
            }
        }

        $action = [
            'Action' => 'Originate',
            'ActionID' => $actionId,
            'Channel' => $channel,
            'Context' => $context,
            'Exten' => $extension,
            'Priority' => (string) $priority,
            'Timeout' => (string) $timeoutMs,
            'Async' => 'true',
        ];

        if ($pairs) {
            // AMI requires one Variable: header per variable, not comma-joined.
            $action['Variable'] = $pairs;
        }

        $this->sendAction($action);
    }

    public function readEvents(float $timeoutSeconds = 0.2): array
    {
        if (!$this->socket) {
            return [];
        }

        $read = [$this->socket];
        $write = null;
        $except = null;
        $seconds = (int) floor($timeoutSeconds);
        $microseconds = (int) (($timeoutSeconds - $seconds) * 1000000);

        $ready = @stream_select($read, $write, $except, $seconds, $microseconds);
        if ($ready === false || $ready === 0) {
            return [];
        }

        $chunk = fread($this->socket, 65535);
        if ($chunk === false || $chunk === '') {
            // Remote end closed the connection; mark socket as dead so the
            // engine can detect and reconnect on the next cycle.
            fclose($this->socket);
            $this->socket = null;
            return [];
        }

        $this->buffer .= $chunk;
        $events = [];

        while (($pos = strpos($this->buffer, "\r\n\r\n")) !== false) {
            $raw = substr($this->buffer, 0, $pos);
            $this->buffer = substr($this->buffer, $pos + 4);
            $event = $this->parseMessage($raw);
            if ($event) {
                $events[] = $event;
            }
        }

        return $events;
    }

    /**
     * Send an AMI action. Array values produce one header line per element,
     * which is required for multi-value fields such as Variable:.
     */
    private function sendAction(array $action): void
    {
        if (!$this->socket) {
            throw new RuntimeException('AMI socket is not connected.');
        }

        $payload = '';
        foreach ($action as $key => $value) {
            if (is_array($value)) {
                foreach ($value as $v) {
                    $payload .= $key . ': ' . $v . "\r\n";
                }
            } else {
                $payload .= $key . ': ' . $value . "\r\n";
            }
        }
        $payload .= "\r\n";

        $written = @fwrite($this->socket, $payload);
        if ($written === false) {
            // Mark socket dead so the engine reconnects on the next cycle.
            fclose($this->socket);
            $this->socket = null;
            throw new RuntimeException('AMI write failed — connection lost.');
        }
    }

    private function parseMessage(string $raw): array
    {
        $event = [];
        foreach (explode("\r\n", $raw) as $line) {
            if (!str_contains($line, ':')) {
                continue;
            }
            [$key, $value] = explode(':', $line, 2);
            $event[trim($key)] = trim($value);
        }
        return $event;
    }
}

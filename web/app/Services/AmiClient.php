<?php

namespace App\Services;

use RuntimeException;

class AmiClient
{
    /** @var resource|null */
    private $socket = null;

    /**
     * Receive buffer — accumulates raw AMI data until complete messages
     * (delimited by \r\n\r\n) can be parsed out.
     * Cleared on close() so stale data from a dead connection never bleeds
     * into a new session.
     */
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
            'Action'   => 'Login',
            'Username' => $this->username,
            'Secret'   => $this->password,
            'Events'   => 'on',
        ]);

        // Wait up to 5 s for the login response and verify it succeeded.
        $deadline  = microtime(true) + 5;
        $loginBuf  = '';
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
            throw new RuntimeException(
                'AMI authentication failed. Check AMI username/password in .env and manager.conf.'
            );
        }
    }

    public function close(): void
    {
        // Always clear the buffer so stale events from the dead connection
        // are never replayed into the next session after reconnect().
        $this->buffer = '';

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
        int    $priority,
        array  $variables,
        int    $timeoutMs = 30000
    ): void {
        $pairs = [];
        foreach ($variables as $key => $value) {
            if ($value !== null && $value !== '') {
                $pairs[] = $key . '=' . $value;
            }
        }

        $action = [
            'Action'   => 'Originate',
            'ActionID' => $actionId,
            'Channel'  => $channel,
            'Context'  => $context,
            'Exten'    => $extension,
            'Priority' => (string) $priority,
            'Timeout'  => (string) $timeoutMs,
            'Async'    => 'true',
        ];

        if ($pairs) {
            // AMI requires one Variable: header per variable, not comma-joined.
            $action['Variable'] = $pairs;
        }

        $this->sendAction($action);
    }

    /**
     * Drain the entire AMI socket receive buffer and return all complete events.
     *
     * Waits up to $timeoutSeconds for the first byte to arrive (0 = non-blocking),
     * then reads ALL currently available data in a tight loop so no events are
     * left sitting in the OS buffer until the next engine cycle.
     *
     * @return array<int, array<string, string>>
     */
    public function readEvents(float $timeoutSeconds = 0.2): array
    {
        if (!$this->socket) {
            return [];
        }

        // Wait for the first byte (or timeout).
        $read = [$this->socket];
        $w = $e = null;
        $sec  = (int) floor($timeoutSeconds);
        $usec = (int) (($timeoutSeconds - $sec) * 1_000_000);

        if (@stream_select($read, $w, $e, $sec, $usec) <= 0) {
            return [];
        }

        // Drain the entire socket receive buffer — loop until the OS reports
        // no more data immediately available.  A single fread() of 65 KB can
        // leave data behind on a busy Asterisk; reading in a tight loop ensures
        // every pending event is captured in this call rather than being delayed
        // to the next engine cycle (which would cause OriginateResponse to be
        // processed after PredictiveAnswered and miss the uniqueid).
        do {
            $chunk = fread($this->socket, 65535);

            if ($chunk === false) {
                // Hard read error — mark dead.
                fclose($this->socket);
                $this->socket = null;
                break;
            }

            if ($chunk === '') {
                // Non-blocking: no more data right now.
                if (feof($this->socket)) {
                    fclose($this->socket);
                    $this->socket = null;
                }
                break;
            }

            $this->buffer .= $chunk;

            // Peek: is more data immediately available?
            $r = [$this->socket];
            $w = $e = null;
        } while ($this->socket !== null && @stream_select($r, $w, $e, 0, 0) > 0);

        // Parse all complete AMI messages out of the buffer.
        $events = [];
        while (($pos = strpos($this->buffer, "\r\n\r\n")) !== false) {
            $raw           = substr($this->buffer, 0, $pos);
            $this->buffer  = substr($this->buffer, $pos + 4);
            $event         = $this->parseMessage($raw);
            if ($event) {
                $events[] = $event;
            }
        }

        return $events;
    }

    /**
     * Send an AMI action.
     *
     * Handles multi-value fields (e.g. Variable:) by emitting one header line
     * per array element.  Uses a write loop to handle partial writes on the
     * non-blocking socket — without this, a large action payload could be
     * silently truncated when the kernel send buffer is momentarily full.
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

        // Write loop: fwrite() on a non-blocking socket may write fewer bytes
        // than requested when the kernel send buffer is full.
        $total   = strlen($payload);
        $written = 0;
        while ($written < $total) {
            $sent = @fwrite($this->socket, substr($payload, $written));
            if ($sent === false || $sent === 0) {
                fclose($this->socket);
                $this->socket = null;
                throw new RuntimeException('AMI write failed — connection lost.');
            }
            $written += $sent;
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

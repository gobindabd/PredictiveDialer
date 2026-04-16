<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Login · Predictive Dialer</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        :root {
            --nav: #0c3036;
            --accent: #1f7a8c;
            --page: #eef4f7;
        }
        body {
            min-height: 100vh;
            margin: 0;
            background:
                radial-gradient(circle at 10% 10%, rgba(31, 122, 140, .18), transparent 28%),
                linear-gradient(135deg, #f8fbfc 0%, var(--page) 100%);
            display: grid;
            place-items: center;
            font-family: Arial, sans-serif;
        }
        .auth-card {
            width: min(440px, calc(100vw - 32px));
            background: #fff;
            border: 1px solid #d8e1e8;
            border-radius: 8px;
            box-shadow: 0 24px 60px rgba(12, 48, 54, .16);
            padding: 32px;
        }
        .auth-brand {
            display: flex;
            gap: 16px;
            align-items: center;
            margin-bottom: 28px;
        }
        .brand-mark {
            width: 54px;
            height: 54px;
            border-radius: 8px;
            background: var(--nav);
            color: #fff;
            display: grid;
            place-items: center;
            font-weight: 800;
            letter-spacing: 0;
        }
        h1 {
            font-size: 26px;
            margin: 0;
            letter-spacing: 0;
        }
        p {
            color: #627181;
            margin: 4px 0 0;
        }
        .btn-primary {
            background: var(--accent);
            border-color: var(--accent);
            border-radius: 6px;
        }
        .form-control {
            border-radius: 6px;
        }
    </style>
</head>
<body>
    <?php require $viewFile; ?>
</body>
</html>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - Sistem Penggajian</title>

    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">

    <style>
        body {
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            background: #1e293b;
            background: linear-gradient(160deg, #5479b4 0%, #6d85bd 100%);
        }

        .login-card {
            width: 100%;
            max-width: 380px;
            border: none;
            border-radius: 16px;
            box-shadow: 0 10px 40px rgba(0, 0, 0, .25);
            overflow: hidden;
        }

        .login-brand {
            background: #1e293b;
            color: #fff;
            text-align: center;
            padding: 32px 24px 28px;
        }

        .login-brand i {
            font-size: 2rem;
            color: #60a5fa;
        }

        .login-brand h4 {
            margin: 8px 0 0;
            font-weight: 600;
        }

        .login-brand small {
            color: #94a3b8;
        }

        .login-body {
            background: #fff;
            padding: 28px 28px 24px;
        }

        .btn-primary-custom {
            background: #2563eb;
            border: none;
        }

        .btn-primary-custom:hover {
            background: #1d4ed8;
        }

        .hint-box {
            background: #f4f6f9;
            border-radius: 8px;
            padding: 10px 14px;
            font-size: .8rem;
            color: #64748b;
        }
    </style>
</head>
<body>
    <div class="login-card card">
        <div class="login-brand">
            <i class="bi bi-cash-stack"></i>
            <h4>Payroll</h4>
            <small>Sistem Informasi Penggajian</small>
        </div>

        <div class="login-body">
            @if ($errors->any())
                <div class="alert alert-danger py-2 small mb-3">{{ $errors->first() }}</div>
            @endif

            <form method="POST" action="{{ route('login.attempt') }}">
                @csrf
                <div class="mb-3">
                    <label class="form-label small text-secondary">Email</label>
                    <input type="email" name="email" value="{{ old('email') }}" class="form-control" required autofocus>
                </div>
                <div class="mb-3">
                    <label class="form-label small text-secondary">Password</label>
                    <input type="password" name="password" class="form-control" required>
                </div>
                <button class="btn btn-primary-custom w-100 text-white" type="submit">
                    <i class="bi bi-box-arrow-in-right"></i> Masuk
                </button>
            </form>

            <div class="hint-box mt-3">
                Admin (HRD): <code>admin@payroll.test</code><br>
                Karyawan: gunakan email yang didaftarkan HRD.
            </div>
        </div>
    </div>
</body>
</html>

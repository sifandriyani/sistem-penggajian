<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>@yield('title', 'Sistem Penggajian')</title>

    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">

    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">

    <style>

        body{
            overflow-x:hidden;
            background:#f4f6f9;
        }

        .sidebar{

            width:250px;

            min-height:100vh;

            background:#1e293b;

            position:fixed;

            left:0;

            top:0;

        }

        .sidebar a{

            color:white;

            text-decoration:none;

            display:block;

            padding:14px 20px;

            transition:.3s;

        }

        .sidebar a:hover{

            background:#334155;

        }

        .sidebar .active{

            background:#2563eb;

        }

        .content{

            margin-left:250px;

        }

        .navbar-custom{

            background:white;

            box-shadow:0 0 10px rgba(0,0,0,.08);

        }

        .card-dashboard{

            border:none;

            border-radius:12px;

            box-shadow:0 3px 10px rgba(0,0,0,.08);

        }

    </style>

</head>

<body>

@include('layouts.sidebar')

<div class="content">

@include('layouts.navbar')

<div class="container-fluid p-4">

@yield('content')

</div>

</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>

</body>

</html>
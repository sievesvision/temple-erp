<!DOCTYPE html>
<html>
<head>
    <title>Temple Management System</title>

    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body>

<nav class="navbar navbar-expand-lg navbar-dark bg-dark">
    <div class="container">
        <a class="navbar-brand" href="#">
            Shree Krishna Temple Udupi
        </a>
    </div>
</nav>

<section class="py-5 text-center">
    <div class="container">
        <h1>Welcome to Temple Management System</h1>

        <p>
            Online Pooja Booking, Donations and Temple Services
        </p>

        <a href="{{ route('login') }}" class="btn btn-primary">
            Login
        </a>

        <a href="{{ route('register') }}" class="btn btn-warning">
            Register
        </a>
    </div>
</section>

</body>
</html>
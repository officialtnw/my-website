<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>MoveEazy - Smooth Moving & Delivery</title>
    <link rel="stylesheet" href="styles.css">
</head>
<body>
    <header>
    <nav>
        <div class="logo"><img src="https://i.imgur.com/ocTusnm.png" alt="MoveEazy Logo"></div>
        <ul>
            <li><a href="#home">Home</a></li>
            <li><a href="services.html">Services</a></li> <!-- updated clearly -->
            <li><a href="#quote">Get a Quote</a></li>
            <li><a href="#about">About</a></li>
            <li><a href="#contact">Contact</a></li>
        </ul>
    </nav>
</header>
    <section id="home" class="hero">
        <div class="hero-content">
            <h1>Moving Made Easy</h1>
            <p>Fast, reliable, and hassle-free furniture delivery and moving services.</p>
        </div>
        <div class="quote-box">
            <h2>Get a Free Estimate</h2>
            <form action="submit.php" method="post" enctype="multipart/form-data">
                <input type="text" name="name" placeholder="Name *" required>
                <input type="email" name="email" placeholder="Email *" required>
                <input type="text" name="phone" placeholder="Phone Number *" required>
                <input type="text" name="pickup" placeholder="Pickup Location *" required>
                <input type="text" name="dropoff" placeholder="Drop-off Location *" required>
                <select name="items" required>
                    <option value="">Number of Items *</option>
                    <option value="1-5">1-5</option>
                    <option value="6-10">6-10</option>
                    <option value="11-20">11-20</option>
                    <option value="more">More than 20</option>
                </select>
                <input type="file" name="images[]" accept="image/*" multiple>
                <button type="submit">Get a Quote</button>
            </form>
        </div>
    </section>
    <footer>
        <p>&copy; 2025 MoveEazy. All Rights Reserved.</p>
    </footer>
</body>
</html>

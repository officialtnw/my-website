<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>MoveEazy Services</title>
    <link rel="stylesheet" href="styles.css">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;600&display=swap" rel="stylesheet">
    <style>
        body {
            font-family: 'Poppins', sans-serif;
            margin: 0; padding: 0;
            background-color: #f8f8f8;
            color: #333;
        }
        header {
            background: #fff; padding: 15px; position: fixed; width: 100%; top:0; left:0; z-index: 1000;
        }
        nav {
            display: flex; justify-content: space-between; align-items: center; max-width: 1200px; margin: auto; padding: 0 20px;
        }
        .logo img {
            height: 80px;
        }
        nav ul {
            list-style: none; display: flex; gap: 20px;
        }
        nav ul li a {
            color: black; text-decoration: none; font-size: 16px; font-weight: 600; padding: 10px 15px;
        }
        nav ul li a:hover {
            background: #ff6600; color: white; border-radius: 5px;
        }
        .services-container {
            max-width: 1200px; margin: 100px auto 50px; padding: 20px;
        }
        .services-container h2 {
            font-size: 36px; text-align: center; margin-bottom: 40px;
        }
        .service {
            background-color: white; padding: 20px; border-radius: 10px; margin-bottom: 20px; display: flex; gap: 20px; align-items: center;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        .service img {
            width: 200px; height: 150px; border-radius: 10px; object-fit: cover;
        }
        .service-content {
            flex: 1;
        }
        .service-content h3 {
            margin-top: 0; color: #ff6600;
        }
        footer {
            text-align: center; padding: 20px; background: white; margin-top: 20px;
        }
    </style>
</head>
<body>
    <header>
        <nav>
            <div class="logo"><img src="https://i.imgur.com/ocTusnm.png" alt="MoveEazy Logo"></div>
            <ul>
                <li><a href="index">Home</a></li>
                <li><a href="services">Services</a></li>
                <li><a href="index.html#quote">Get a Quote</a></li>
                <li><a href="#about">About</a></li>
                <li><a href="#contact">Contact</a></li>
            </ul>
        </nav>
    </header>

    <div class="services-container">
        <h2>Our Services</h2>
        <div class="service">
            <img src="https://i.imgur.com/Z9WKLXp.jpg" alt="Furniture Delivery">
            <div class="service-content">
                <h3>Furniture Pickup & Delivery</h3>
                <p>Efficient and secure furniture pickup and delivery directly from the store or your location, ensuring your items arrive safely and on time.</p>
            </div>
        </div>
        <div class="service">
            <img src="https://i.imgur.com/L25K5dH.jpg" alt="Residential Moving">
            <div class="service-content">
                <h3>Residential Moving</h3>
                <p>Professional residential moving services tailored to your needs, making your relocation stress-free and easy.</p>
            </div>
        </div>
        <div class="service">
            <img src="https://i.imgur.com/o3p2C0G.jpg" alt="Office Relocation">
            <div class="service-content">
                <h3>Office Relocation</h3>
                <p>Reliable and timely office relocation services to minimize downtime and disruption to your business.</p>
            </div>
        </div>
    </div>

    <footer>
        <p>&copy; 2025 MoveEazy. All Rights Reserved.</p>
    </footer>
</body>
</html>

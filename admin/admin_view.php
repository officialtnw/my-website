<?php
// Database connection
$conn = new mysqli("localhost", "root", "lol_123", "moveeazy_db");
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Fetch all requests
$result = $conn->query("SELECT * FROM quotes ORDER BY submitted_at DESC");
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>MoveEazy Admin - Quote Requests</title>
    <style>
        body {
            font-family: 'Poppins', sans-serif;
            margin: 20px;
            background-color: #f4f4f4;
        }
        h1 {
            text-align: center;
            color: #333;
        }
        .container {
            max-width: 1200px;
            margin: 0 auto;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            background-color: #fff;
            box-shadow: 0 2px 5px rgba(0,0,0,0.1);
        }
        th, td {
            padding: 12px;
            text-align: left;
            border-bottom: 1px solid #ddd;
        }
        th {
            background-color: #007bff;
            color: white;
            cursor: pointer;
        }
        th:hover {
            background-color: #0056b3;
        }
        tr:nth-child(even) {
            background-color: #f9f9f9;
        }
        tr:hover {
            background-color: #f1f1f1;
        }
        img {
            max-width: 200px;
            height: auto;
            display: block;
            margin-top: 5px;
        }
        .no-data {
            text-align: center;
            padding: 20px;
            color: #666;
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>MoveEazy Admin - Quote Requests</h1>
        <?php if ($result->num_rows > 0): ?>
            <table>
                <thead>
                    <tr>
                        <th>#</th>
                        <th>Name</th>
                        <th>Email</th>
                        <th>Phone</th>
                        <th>Pickup</th>
                        <th>Dropoff</th>
                        <th>Items</th>
                        <th>Image</th>
                        <th>Submitted</th>
                    </tr>
                </thead>
                <tbody>
                    <?php
                    $rowNumber = 1;
                    while ($row = $result->fetch_assoc()) {
                        echo "<tr>";
                        echo "<td>" . $rowNumber++ . "</td>";
                        echo "<td>" . htmlspecialchars($row['name']) . "</td>";
                        echo "<td>" . htmlspecialchars($row['email']) . "</td>";
                        echo "<td>" . htmlspecialchars($row['phone']) . "</td>";
                        echo "<td>" . htmlspecialchars($row['pickup']) . "</td>";
                        echo "<td>" . htmlspecialchars($row['dropoff']) . "</td>";
                        echo "<td>" . htmlspecialchars($row['items']) . "</td>";
                        echo "<td>";
                        if ($row['image_paths']) {
                            $imagePaths = explode(',', $row['image_paths']);
                            foreach ($imagePaths as $path) {
                                // Remove 'uploads/' prefix if present
                                $cleanPath = preg_replace('#^uploads/#', '', trim($path));
                                $fullPath = '../uploads/' . $cleanPath; // Path for file_exists
                                $displayPath = '../uploads/' . $cleanPath; // Path for <img> src
                                if (file_exists($fullPath)) {
                                    echo "<img src='$displayPath' alt='Uploaded Image'>";
                                } else {
                                    echo "No image found";
                                }
                            }
                        } else {
                            echo "No image";
                        }
                        echo "</td>";
                        echo "<td>" . htmlspecialchars($row['submitted_at']) . "</td>";
                        echo "</tr>";
                    }
                    ?>
                </tbody>
            </table>
        <?php else: ?>
            <p class="no-data">No quote requests found.</p>
        <?php endif; ?>
    </div>

    <?php $conn->close(); ?>

    <script>
        document.querySelectorAll('th').forEach(header => {
            header.addEventListener('click', () => {
                const table = header.closest('table');
                const index = Array.from(header.parentElement.children).indexOf(header);
                const rows = Array.from(table.querySelector('tbody').rows);
                const isAscending = header.classList.toggle('asc');
                
                rows.sort((a, b) => {
                    const aText = a.cells[index].textContent.trim();
                    const bText = b.cells[index].textContent.trim();
                    return isAscending 
                        ? aText.localeCompare(bText, undefined, {numeric: true})
                        : bText.localeCompare(aText, undefined, {numeric: true});
                });
                
                rows.forEach(row => table.querySelector('tbody').appendChild(row));
            });
        });
    </script>
</body>
</html>
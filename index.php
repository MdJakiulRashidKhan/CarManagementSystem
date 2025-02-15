<?php
// Database connection parameters
define('DB_HOST', 'localhost');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_NAME', 'car_management');
define('CAR_TABLE', 'cars');  

// Establishing a MySQLi connection
class Database {
    private $conn;

    public function __construct() {
        // Create a connection to MySQL server without selecting the database
        $this->conn = new mysqli(DB_HOST, DB_USER, DB_PASS);

        // Check connection
        if ($this->conn->connect_error) {
            die("Connection failed: " . $this->conn->connect_error);
        }

        // Check if the database exists
        if (!$this->databaseExists()) {
            // If database doesn't exist, create it
            $this->createDatabase();
        }

        // Select the database after ensuring it exists
        $this->conn->select_db(DB_NAME);

        // Create the table if it doesn't exist
        $this->createTable();
    }

    private function databaseExists() {
        // Check if the database exists
        $result = $this->conn->query("SHOW DATABASES LIKE '" . DB_NAME . "'");
        return $result->num_rows > 0;
    }

    private function createDatabase() {
        // Create the database if it doesn't exist
        $sql = "CREATE DATABASE " . DB_NAME;
        if ($this->conn->query($sql)) {
            echo "Database created successfully\n";
        } else {
            die("Error creating database: " . $this->conn->error);
        }
    }

    private function createTable() {
        // Create the 'cars' table if it doesn't exist
        $sql = "CREATE TABLE IF NOT EXISTS " . CAR_TABLE . " (
            id INT(11) AUTO_INCREMENT PRIMARY KEY,
            brand VARCHAR(100) NOT NULL,
            model VARCHAR(100) NOT NULL,
            year INT(4) NOT NULL,
            price DECIMAL(10,2) NOT NULL
        )";

        if ($this->conn->query($sql)) {
            echo "Table '" . CAR_TABLE . "' created successfully\n";
        } else {
            die("Error creating table: " . $this->conn->error);
        }
    }

    public function getConnection() {
        return $this->conn;
    }
}

// Define the interface
interface CarInterface {
    public function insert($brand, $model, $year, $price);
    public function getAll($limit = 10, $offset = 0);
    public function update($id, $brand, $model, $year, $price);
    public function delete($id);
    public function search($search, $limit = 10, $offset = 0);
}

// Abstract Class that implements the CarInterface
abstract class AbstractCar implements CarInterface {
    protected $db;

    public function __construct() {
        $this->db = (new Database())->getConnection();
    }

    // Make findCarById public
    public function findCarById($id) {
        $stmt = $this->db->prepare("SELECT * FROM " . CAR_TABLE . " WHERE id = ?");
        $stmt->bind_param("i", $id);
        $stmt->execute();
        return $stmt->get_result()->fetch_assoc();
    }
}

// Concrete Car Class implementing AbstractCar
class Car extends AbstractCar {
    // Insert a new car into the database
    public function insert($brand, $model, $year, $price) {
        $stmt = $this->db->prepare("INSERT INTO " . CAR_TABLE . " (brand, model, year, price) VALUES (?, ?, ?, ?)");
        $stmt->bind_param("ssid", $brand, $model, $year, $price);
        return $stmt->execute();
    }

    // Retrieve all cars from the database
    public function getAll($limit = 10, $offset = 0) {
        $stmt = $this->db->prepare("SELECT * FROM " . CAR_TABLE . " LIMIT ?, ?");
        $stmt->bind_param("ii", $offset, $limit);
        $stmt->execute();
        return $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    }

    // Update car details by id
    public function update($id, $brand, $model, $year, $price) {
        $stmt = $this->db->prepare("UPDATE " . CAR_TABLE . " SET brand = ?, model = ?, year = ?, price = ? WHERE id = ?");
        $stmt->bind_param("ssidi", $brand, $model, $year, $price, $id);
        return $stmt->execute();
    }

    // Delete a car by id
    public function delete($id) {
        $stmt = $this->db->prepare("DELETE FROM " . CAR_TABLE . " WHERE id = ?");
        $stmt->bind_param("i", $id);
        return $stmt->execute();
    }

    // Search for cars based on brand/model
    public function search($search, $limit = 10, $offset = 0) {
        $stmt = $this->db->prepare("SELECT * FROM " . CAR_TABLE . " WHERE brand LIKE ? OR model LIKE ? LIMIT ?, ?");
        $searchTerm = "%$search%";
        $stmt->bind_param("ssii", $searchTerm, $searchTerm, $offset, $limit);
        $stmt->execute();
        return $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    }

    // Get total number of cars for pagination
    public function getCount() {
        $result = $this->db->query("SELECT COUNT(*) as count FROM " . CAR_TABLE);
        return $result->fetch_assoc()['count'];
    }
}

// Create a new car instance
$car = new Car();

// Handle insert/update/delete requests
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $id = $_POST['id'] ?? null;
    $brand = $_POST['brand'];
    $model = $_POST['model'];
    $year = $_POST['year'];
    $price = $_POST['price'];

    if ($id) {
        // Update car
        $car->update($id, $brand, $model, $year, $price);
    } else {
        // Insert new car
        $car->insert($brand, $model, $year, $price);
    }

    // Redirect to the same page to avoid form resubmission
    header("Location: " . $_SERVER['PHP_SELF']);
    exit();
}

// Handle delete request
if (isset($_GET['delete'])) {
    // Delete car
    $car->delete($_GET['delete']);

    // Redirect to the same page to avoid form resubmission
    header("Location: " . $_SERVER['PHP_SELF']);
    exit();
}

// Handle search
$search = $_GET['search'] ?? '';
$cars = $car->search($search);
if (!$search) {
    $cars = $car->getAll();
}

// Handle pagination
$limit = 10;
$page = $_GET['page'] ?? 1;
$offset = ($page - 1) * $limit;
$cars = $car->search($search, $limit, $offset);
$totalCars = $car->getCount();
$totalPages = ceil($totalCars / $limit);

// If editing, get car data
$carData = null;
if (isset($_GET['edit'])) {
    // Access the public method to get the car by ID
    $carData = $car->findCarById($_GET['edit']);
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Car Management System</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/css/bootstrap.min.css" rel="stylesheet">
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
</head>
<body>
<div class="container mt-5">
    <h2>Car Management System</h2>

    <!-- Display table name -->
    <p>Currently managing cars in the table: <strong><?= CAR_TABLE ?></strong></p>

    <!-- Search Form -->
    <form method="GET" class="mb-4">
        <input type="text" name="search" placeholder="Search by brand/model" class="form-control mb-2" value="<?= htmlspecialchars($search) ?>">
        <button type="submit" class="btn btn-secondary">Search</button>
    </form>

    <!-- Car Form -->
    <form method="POST" class="mb-4" id="carForm">
        <input type="hidden" name="id" value="<?= isset($carData['id']) ? $carData['id'] : '' ?>">
        <div class="mb-3">
            <label for="brand" class="form-label">Brand</label>
            <input type="text" class="form-control" id="brand" name="brand" required value="<?= isset($carData['brand']) ? htmlspecialchars($carData['brand']) : '' ?>">
        </div>
        <div class="mb-3">
            <label for="model" class="form-label">Model</label>
            <input type="text" class="form-control" id="model" name="model" required value="<?= isset($carData['model']) ? htmlspecialchars($carData['model']) : '' ?>">
        </div>
        <div class="mb-3">
            <label for="year" class="form-label">Year</label>
            <input type="number" class="form-control" id="year" name="year" required value="<?= isset($carData['year']) ? htmlspecialchars($carData['year']) : '' ?>">
        </div>
        <div class="mb-3">
            <label for="price" class="form-label">Price</label>
            <input type="number" class="form-control" id="price" name="price" required value="<?= isset($carData['price']) ? htmlspecialchars($carData['price']) : '' ?>">
        </div>
        <button type="submit" class="btn btn-primary">Submit</button>
    </form>

    <!-- Car List -->
    <table class="table">
        <thead>
            <tr>
                <th scope="col">ID</th>
                <th scope="col">Brand</th>
                <th scope="col">Model</th>
                <th scope="col">Year</th>
                <th scope="col">Price</th>
                <th scope="col">Actions</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($cars as $car) : ?>
                <tr>
                    <td><?= $car['id'] ?></td>
                    <td><?= $car['brand'] ?></td>
                    <td><?= $car['model'] ?></td>
                    <td><?= $car['year'] ?></td>
                    <td><?= $car['price'] ?></td>
                    <td>
                        <a href="?edit=<?= $car['id'] ?>" class="btn btn-warning btn-sm">Edit</a>
                        <a href="?delete=<?= $car['id'] ?>" class="btn btn-danger btn-sm">Delete</a>
                    </td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>

    <!-- Pagination -->
    <nav aria-label="Page navigation">
        <ul class="pagination">
            <li class="page-item <?= $page == 1 ? 'disabled' : '' ?>">
                <a class="page-link" href="?page=1&search=<?= urlencode($search) ?>" tabindex="-1">First</a>
            </li>
            <li class="page-item <?= $page == 1 ? 'disabled' : '' ?>">
                <a class="page-link" href="?page=<?= $page - 1 ?>&search=<?= urlencode($search) ?>">Previous</a>
            </li>
            <li class="page-item <?= $page == $totalPages ? 'disabled' : '' ?>">
                <a class="page-link" href="?page=<?= $page + 1 ?>&search=<?= urlencode($search) ?>">Next</a>
            </li>
            <li class="page-item <?= $page == $totalPages ? 'disabled' : '' ?>">
                <a class="page-link" href="?page=<?= $totalPages ?>&search=<?= urlencode($search) ?>">Last</a>
            </li>
        </ul>
    </nav>
</div>

<script>
$(document).ready(function() {
    // Client-side validation for the car form
    $('#carForm').on('submit', function(e) {
        var brand = $('#brand').val();
        var model = $('#model').val();
        var year = $('#year').val();
        var price = $('#price').val();

        if (!brand || !model || !year || !price) {
            e.preventDefault();
            alert('All fields are required.');
        }
    });
});
</script>
</body>
</html>

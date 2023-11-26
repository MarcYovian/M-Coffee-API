<?php
use Ramsey\Uuid\Uuid;

include "connection.php";
require 'vendor/autoload.php';

function validateInput($input, $type, $options = [], $min = null, $max = null) {
    switch ($type) {
        case 'image_extension':
            // Mengecek apakah input memiliki ekstensi gambar yang diizinkan
            $allowedExtensions = ['jpg', 'jpeg', 'png'];
            $fileExtension = strtolower(pathinfo($input, PATHINFO_EXTENSION));
            return in_array($fileExtension, $allowedExtensions);
        case 'numeric':
            $numericValue = filter_var($input, FILTER_VALIDATE_INT);
            return validateNumeric($numericValue, $min, $max, "Invalid number value");
        case 'float':
            $floatValue = filter_var($input, FILTER_VALIDATE_FLOAT);
            return validateNumeric($floatValue, $min, $max, "Invalid number value");
        case 'enum':
            return in_array($input, $options);
        default:
            return true; // Default to true for other types
    }
}

function validateNumeric($value, $min, $max, $errorMessage){
    if($value === false || ($min !== null && $value < $min) || ($max !== null && $value > $max) ){
        return responseJson(400, $errorMessage);
    }
    return $value;
}

function getCurrentTimestamp() {
    return date("Y-m-d H:i:s");
}

function responseJson($code, $message, $data = null){
    http_response_code($code);
    echo json_encode(array("code" => $code, "message" => $message, "data" => $data));
    exit;
}

function isUuidValid($id){
    return Uuid::isValid($id);
}

function doesUuidExistInDatabase($id, $koneksi){
    $checkExistence = "SELECT COUNT(*) as count FROM product WHERE id=?";
    $stmtExistence = $koneksi->prepare($checkExistence);
    $stmtExistence->bind_param("s", $id);
    $stmtExistence->execute();
    $resultExistence = $stmtExistence->get_result();
    $rowExistence = $resultExistence->fetch_assoc();

    return $rowExistence["count"] > 0;
}

// Create data
if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $myuuid = Uuid::uuid4()->toString();

    $name = $_POST["name"];
    $image = validateInput($_POST["image"], 'image_extension') ? $_POST["image"] : responseJson(400, "Invalid image file extension");
    $price = validateInput($_POST["price"], 'numeric', [], 0 ) ? $_POST["price"] : responseJson(400, "Invalid price or negative price");
    $discount = validateInput($_POST["discount"], 'float', [], 0, 100) ? $_POST["discount"] : responseJson(400, "Invalid discount percentage");
    $description = $_POST["description"];
    $rating = validateInput($_POST["rating"], 'float', [], 0, 5) ? $_POST["rating"] : responseJson(400, "Invalid rating or negative rating or more than 5");
    $numOfSales = validateInput($_POST["numOfSales"], 'numeric', [], 0) ? $_POST["numOfSales"] : responseJson(400, "Invalid number of sales or negative number");

    $allowedCategories = ['coffee', 'noodle', 'rice', 'snack', 'spaghetti', 'tea', 'toast'];
    $category = validateInput($_POST["category"], 'enum', $allowedCategories ) ? $_POST["category"] : responseJson(400, "Invalid category");

    $createdAt = getCurrentTimestamp();
    $updatedAt = getCurrentTimestamp();

    $sql = "INSERT INTO product(id, name, image, price, discount, description, rating, numOfSales, category, created_at, updated_at) VALUES ('$myuuid', '$name', '$image', '$price', '$discount', '$description', '$rating', '$numOfSales', '$category', '$createdAt', '$updatedAt')";

    if ($koneksi->query($sql) === TRUE) {
        $productDetails = [
            "id" => $myuuid,
            "name" => $name,
            "image" => $image,
            "price" => $price,
            "discount" => $discount,
            "description" => $description,
            "rating" => $rating,
            "numOfSales" => $numOfSales,
            "category" => $category,
            "createdAt" => $createdAt,
            "updatedAt" => $updatedAt
        ];

        responseJson(200, "Data berhasil ditambahkan", $productDetails);
    } else {
        responseJson(500, "Error: " . $sql . "<br>" . $koneksi->error);
    }
}

// Get All Data;
if ($_SERVER["REQUEST_METHOD"] === "GET") {
    $result = $koneksi->query("SELECT * FROM product");
    $data = array();

    while ($row = $result->fetch_assoc()) {
        $data[] = $row;
    }

    responseJson(200, "Get All Data Successfully", $data);
}

// Update Data
if ($_SERVER["REQUEST_METHOD"] === "PUT") {
    parse_str(file_get_contents("php://input"), $_PUT);
    $id = $_PUT["id"];
    if (!isUuidValid($id)) {
        responseJson(400, "Invalid UUID");
    }

    if (!doesUuidExistInDatabase($id, $koneksi)) {
        responseJson(404, "UUID not found in the database");
    }

    $name = $_PUT["name"];
    $image = validateInput($_PUT["image"], 'url') ? $_PUT["image"] : responseJson(400, "Invalid image URL");
    $price = validateInput($_PUT["price"], 'numeric', [], 0) ? $_PUT["price"] : responseJson(400, "Invalid price or negative price");
    $discount = validateInput($_PUT["discount"], 'float', [], 0, 100) ? $_PUT["discount"] : responseJson(400, "Invalid discount percentage");
    $description = $_PUT["description"];
    $rating = validateInput($_PUT["rating"], 'float', [], 0, 5) ? $_PUT["rating"] : responseJson(400, "Invalid rating or negative rating or more than 5");
    $numOfSales = validateInput($_PUT["numOfSales"], 'numeric', [], 0) ? $_PUT["numOfSales"] : responseJson(400, "Invalid number of sales or negative number");

    $allowedCategories = ['coffee', 'noodle', 'rice', 'snack', 'spaghetti', 'tea', 'toast'];
    $category = validateInput($_PUT["category"], 'enum', $allowedCategories) ? $_PUT["category"] : responseJson(400, "Invalid category");

    $updatedAt = getCurrentTimestamp();

    $sql = "UPDATE product SET name='$name', image='$image', price='$price', discount='$discount', description='$description', rating='$rating', numOfSales='$numOfSales', category='$category', updated_at='$updatedAt' WHERE id='$id'";

    if ($koneksi->query($sql) === TRUE) {
        $productDetails = [
            "id" => $id,
            "name" => $name,
            "image" => $image,
            "price" => $price,
            "discount" => $discount,
            "description" => $description,
            "rating" => $rating,
            "numOfSales" => $numOfSales,
            "category" => $category,
            "updated_at" => $updatedAt
        ];
        responseJson (200, "Update Data Successfully", $productDetails);
    } else {
        responseJson (500, "Error updating record: " . $koneksi->error);
    }
}

// Delete Data
if ($_SERVER["REQUEST_METHOD"] === "DELETE") {
    parse_str(file_get_contents("php://input"), $_DELETE);
    $id = $_DELETE["id"];

    if (!isUuidValid($id)) {
        responseJson(400, "Invalid UUID");
    }

    if (!doesUuidExistInDatabase($id, $koneksi)) {
        responseJson(404, "UUID not found in the database");
    }

    $sql = "DELETE FROM product WHERE id=?";
    $stmt = $koneksi->prepare($sql);
    $stmt->bind_param("s", $id); 

    if ($stmt->execute()) {
        $productDetails = [
            "id" => $id,
        ];

        responseJson(200, "Data berhasil dihapus", $productDetails);
    } else {
        responseJson(500, "Error deleting record: " . $stmt->error);
    }

    $stmt->close();
}
?>

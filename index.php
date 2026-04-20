<?php
$servername = "localhost";
$username = "root";
$password = "";
$database = "CSCI4410";

$conn = new mysqli($servername, $username, $password, $database);

if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

function displayTable($result, $title = "Query Results") {
    if ($result && $result->num_rows > 0) {
        $html = "<div class='table-wrapper'>";
        $html .= "<h2>" . htmlspecialchars($title) . "</h2>";
        $html .= "<table><tr>";

        $fields = $result->fetch_fields();
        foreach ($fields as $field) {
            $html .= "<th>" . htmlspecialchars($field->name) . "</th>";
        }
        $html .= "</tr>";

        $result->data_seek(0);
        while ($row = $result->fetch_assoc()) {
            $html .= "<tr>";
            foreach ($row as $value) {
                $displayValue = ($value === null || $value === "") ? "N/A" : $value;
                $html .= "<td>" . htmlspecialchars((string)$displayValue) . "</td>";
            }
            $html .= "</tr>";
        }

        $html .= "</table></div>";
        return $html;
    }

    return "<p class='message info'>No records found.</p>";
}

$messages = [];
$tableOutput = "";
$showInsertForm = false;
$showDeleteForm = false;
$showUpdateForm = false;

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    if (isset($_POST["display_all"])) {
        $result = $conn->query("SELECT * FROM Students ORDER BY ID");
        $tableOutput = displayTable($result, "All Students");
    } elseif (isset($_POST["display_male"])) {
        $result = $conn->query("SELECT * FROM Students WHERE Gender = 'Male' ORDER BY Name");
        $tableOutput = displayTable($result, "Male Students");
    } elseif (isset($_POST["display_female"])) {
        $result = $conn->query("SELECT * FROM Students WHERE Gender = 'Female' ORDER BY Name");
        $tableOutput = displayTable($result, "Female Students");
    } elseif (isset($_POST["display_older"])) {
        $result = $conn->query("SELECT * FROM Students WHERE Age > 21 ORDER BY Age DESC");
        $tableOutput = displayTable($result, "Students Older Than 21");
    } elseif (isset($_POST["count_majors"])) {
        $result = $conn->query("SELECT COUNT(DISTINCT Major) AS DistinctMajors FROM Students");
        $tableOutput = displayTable($result, "Count of Distinct Majors");
    } elseif (isset($_POST["display_no_phone"])) {
        $result = $conn->query("SELECT * FROM Students WHERE Phone IS NULL ORDER BY Name");
        $tableOutput = displayTable($result, "Students Without Phone Numbers");
    } elseif (isset($_POST["show_insert_form"])) {
        $showInsertForm = true;
    } elseif (isset($_POST["show_delete_form"])) {
        $showDeleteForm = true;
    } elseif (isset($_POST["show_update_form"])) {
        $showUpdateForm = true;
    } elseif (isset($_POST["insert_student"])) {
        $showInsertForm = true;

        $name = trim($_POST["Name"] ?? "");
        $blueCard = trim($_POST["BlueCard"] ?? "");
        $major = trim($_POST["Major"] ?? "");
        $classLevel = trim($_POST["ClassLevel"] ?? "");
        $email = trim($_POST["Email"] ?? "");
        $gender = trim($_POST["Gender"] ?? "");
        $age = (int)($_POST["Age"] ?? 0);
        $phone = trim($_POST["Phone"] ?? "");

        if ($name === "" || $blueCard === "" || $major === "" || $classLevel === "" || $email === "" || $gender === "" || $age <= 0) {
            $messages[] = ["type" => "error", "text" => "Please complete all required fields before inserting a student."];
        } else {
            if ($phone === "") {
                $stmt = $conn->prepare("INSERT INTO Students (Name, BlueCard, Major, ClassLevel, Email, Gender, Age, Phone) VALUES (?, ?, ?, ?, ?, ?, ?, NULL)");
                $stmt->bind_param("ssssssi", $name, $blueCard, $major, $classLevel, $email, $gender, $age);
            } else {
                $stmt = $conn->prepare("INSERT INTO Students (Name, BlueCard, Major, ClassLevel, Email, Gender, Age, Phone) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
                $stmt->bind_param("ssssssis", $name, $blueCard, $major, $classLevel, $email, $gender, $age, $phone);
            }

            if ($stmt->execute()) {
                $messages[] = ["type" => "success", "text" => "New student inserted successfully."];
                $showInsertForm = false;
                $result = $conn->query("SELECT * FROM Students ORDER BY ID");
                $tableOutput = displayTable($result, "Updated Student List");
            } else {
                $messages[] = ["type" => "error", "text" => "Insert failed: " . $stmt->error];
            }

            $stmt->close();
        }
    } elseif (isset($_POST["delete_student"])) {
        $showDeleteForm = true;
        $blueCard = trim($_POST["delete_bluecard"] ?? "");

        if ($blueCard === "") {
            $messages[] = ["type" => "error", "text" => "Please enter a BlueCard ID to delete."];
        } else {
            $stmt = $conn->prepare("DELETE FROM Students WHERE BlueCard = ?");
            $stmt->bind_param("s", $blueCard);

            if ($stmt->execute()) {
                if ($stmt->affected_rows > 0) {
                    $messages[] = ["type" => "success", "text" => "Student deleted successfully."];
                    $showDeleteForm = false;
                    $result = $conn->query("SELECT * FROM Students ORDER BY ID");
                    $tableOutput = displayTable($result, "Updated Student List");
                } else {
                    $messages[] = ["type" => "info", "text" => "No student found with that BlueCard ID."];
                }
            } else {
                $messages[] = ["type" => "error", "text" => "Delete failed: " . $stmt->error];
            }

            $stmt->close();
        }
    } elseif (isset($_POST["update_phone"])) {
        $showUpdateForm = true;
        $blueCard = trim($_POST["update_bluecard"] ?? "");
        $newPhone = trim($_POST["new_phone"] ?? "");

        if ($blueCard === "") {
            $messages[] = ["type" => "error", "text" => "Please enter a BlueCard ID to update."];
        } else {
            if ($newPhone === "") {
                $stmt = $conn->prepare("UPDATE Students SET Phone = NULL WHERE BlueCard = ?");
                $stmt->bind_param("s", $blueCard);
            } else {
                $stmt = $conn->prepare("UPDATE Students SET Phone = ? WHERE BlueCard = ?");
                $stmt->bind_param("ss", $newPhone, $blueCard);
            }

            if ($stmt->execute()) {
                if ($stmt->affected_rows > 0) {
                    $messages[] = ["type" => "success", "text" => "Phone number updated successfully."];
                    $showUpdateForm = false;
                    $result = $conn->query("SELECT * FROM Students ORDER BY ID");
                    $tableOutput = displayTable($result, "Updated Student List");
                } else {
                    $messages[] = ["type" => "info", "text" => "No changes were made. Check the BlueCard ID and phone number."];
                }
            } else {
                $messages[] = ["type" => "error", "text" => "Update failed: " . $stmt->error];
            }

            $stmt->close();
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>OLA 6 Student Database</title>
    <link type="text/css" rel="stylesheet" href="ola6.css">
</head>
<body>
    <div class="container">
        <h1>Student Database Control Panel</h1>
        <p class="subtitle">CSCI 4410/5410 Web Technologies</p>

        <?php foreach ($messages as $message): ?>
            <div class="message <?php echo htmlspecialchars($message['type']); ?>">
                <?php echo htmlspecialchars($message['text']); ?>
            </div>
        <?php endforeach; ?>

        <form method="post" class="action-form">
            <button type="submit" name="display_all">Display All Students</button>
            <button type="submit" name="display_male">Display Male Students</button>
            <button type="submit" name="display_female">Display Female Students</button>
            <button type="submit" name="display_older">Display Students Older Than 21</button>
            <button type="submit" name="count_majors">Count Distinct Majors</button>
            <button type="submit" name="display_no_phone">Display Students Without Phone Numbers</button>
            <button type="submit" name="show_insert_form">Insert New Student</button>
            <button type="submit" name="show_delete_form">Delete Student</button>
            <button type="submit" name="show_update_form">Update Phone Number</button>
        </form>

        <?php if ($showInsertForm): ?>
            <div class="form-card">
                <h2>Insert New Student</h2>
                <form method="post" class="data-form">
                    <input type="text" name="Name" placeholder="Full Name" required>
                    <input type="text" name="BlueCard" placeholder="BlueCard ID" required>
                    <input type="text" name="Major" placeholder="Major" required>
                    <input type="text" name="ClassLevel" placeholder="Class Level" required>
                    <input type="email" name="Email" placeholder="Email" required>
                    <select name="Gender" required>
                        <option value="">Select Gender</option>
                        <option value="Male">Male</option>
                        <option value="Female">Female</option>
                    </select>
                    <input type="number" name="Age" placeholder="Age" min="1" required>
                    <input type="text" name="Phone" placeholder="Phone Number (optional)">
                    <button type="submit" name="insert_student">Submit New Student</button>
                </form>
            </div>
        <?php endif; ?>

        <?php if ($showDeleteForm): ?>
            <div class="form-card">
                <h2>Delete Student</h2>
                <form method="post" class="data-form inline-form">
                    <input type="text" name="delete_bluecard" placeholder="Enter BlueCard ID" required>
                    <button type="submit" name="delete_student">Confirm Delete</button>
                </form>
            </div>
        <?php endif; ?>

        <?php if ($showUpdateForm): ?>
            <div class="form-card">
                <h2>Update Phone Number</h2>
                <form method="post" class="data-form inline-form">
                    <input type="text" name="update_bluecard" placeholder="Enter BlueCard ID" required>
                    <input type="text" name="new_phone" placeholder="New Phone Number (leave blank for NULL)">
                    <button type="submit" name="update_phone">Submit Update</button>
                </form>
            </div>
        <?php endif; ?>

        <div class="results-section">
            <?php echo $tableOutput; ?>
        </div>
    </div>
</body>
</html>
<?php
$conn->close();
?>

<?php
session_start();
$conn = new mysqli(
    "localhost",
    "root",
    "",
    "clinic_appointment_db"
);

if ($conn->connect_error) {
    die("Database connection failed: " . $conn->connect_error);
}

$conn->set_charset("utf8mb4");
$errors = [];
$success = "";
$db_error = "";

$patient_name = "";
$email = "";
$phone = "";
$department = "";
$doctor = "";
$appointment_date = "";
$time_slot = "";
$reason = "";

$departments = [
    "General Medicine",
    "Cardiology",
    "Dental",
    "Dermatology",
    "Pediatrics",
    "Gynecology"
];

$doctors = [
    "Dr. John",
    "Dr. Mary",
    "Dr. James",
    "Dr. Sarah"
];

$time_slots = [
    "08:00 AM",
    "09:00 AM",
    "10:00 AM",
    "11:00 AM",
    "12:00 PM",
    "02:00 PM",
    "03:00 PM",
    "04:00 PM"
];

if (isset($_SESSION["success"])) {

    $success = $_SESSION["success"];

    unset($_SESSION["success"]);

}

if ($_SERVER["REQUEST_METHOD"] == "POST") {

    // COLLECT FORM DATA
    $patient_name = trim($_POST["patient_name"] ?? "");
    $email = trim($_POST["email"] ?? "");
    $phone = trim($_POST["phone"] ?? "");
    $department = trim($_POST["department"] ?? "");
    $doctor = trim($_POST["doctor"] ?? "");
    $appointment_date = trim($_POST["appointment_date"] ?? "");
    $time_slot = trim($_POST["time_slot"] ?? "");
    $reason = trim($_POST["reason"] ?? "");

    if (empty($patient_name)) {

        $errors["patient_name"] = "Patient name is required.";

    } elseif (
        !preg_match("/^[\p{L}\s'-]{2,100}$/u", $patient_name)
    ) {

        $errors["patient_name"] = "Enter a valid patient name.";

    }

    if (!empty($email) && !filter_var($email, FILTER_VALIDATE_EMAIL)) {

        $errors["email"] = "Enter a valid email address.";

    }

    if (empty($phone)) {

        $errors["phone"] = "Phone number is required.";

    } elseif (!preg_match('/^\+?[0-9\s()-]{7,20}$/', $phone)) {

        $errors["phone"] = "Enter a valid phone number.";

    }

    if (!in_array($department, $departments, true)) {

        $errors["department"] = "Please select a valid department.";

    }

    if (!in_array($doctor, $doctors, true)) {

        $errors["doctor"] = "Please select a valid doctor.";

    }

    if (empty($appointment_date)) {

        $errors["appointment_date"] = "Appointment date is required.";

    } else {

        $date = DateTime::createFromFormat("!Y-m-d", $appointment_date);

        if (
            !$date ||
            $date->format("Y-m-d") !== $appointment_date
        ) {

            $errors["appointment_date"] = "Enter a valid appointment date.";

        } elseif ($date <= new DateTime("today")) {

            $errors["appointment_date"] = "Appointment must be on a future date.";

        }

    }

    if (!in_array($time_slot, $time_slots, true)) {

        $errors["time_slot"] = "Please select a valid time slot.";

    }

    $reason_length = function_exists("mb_strlen")
        ? mb_strlen($reason, "UTF-8")
        : strlen($reason);

    if (empty($reason)) {

        $errors["reason"] = "Symptoms or reason is required.";

    } elseif ($reason_length < 10 || $reason_length > 500) {

        $errors["reason"] = "Reason must contain 10-500 characters.";

    }

    if (($_POST["consent"] ?? "") !== "1") {

        $errors["consent"] = "You must agree to data use.";

    }

    if (empty($errors)) {

        $sql = "INSERT INTO appointments
        (
            patient_name,
            email,
            phone,
            department,
            doctor,
            appointment_date,
            time_slot,
            reason,
            consent
        )
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, 1)";

        $stmt = $conn->prepare($sql);

        if ($stmt) {

            $stmt->bind_param(
                "ssssssss",
                $patient_name,
                $email,
                $phone,
                $department,
                $doctor,
                $appointment_date,
                $time_slot,
                $reason
            );

            if ($stmt->execute()) {

                $_SESSION["success"] =
                    "Appointment booked successfully! Your appointment is pending confirmation.";

                header("Location: index.php");
                exit();

            } else {

                if ($stmt->errno == 1062) {

                    $errors["time_slot"] =
                        "This doctor already has an appointment booked for this date and time. Please choose another slot.";

                } else {

                    $db_error = "Unable to save appointment. Please try again.";

                }

            }

            $stmt->close();

        } else {

            $db_error = "Unable to prepare database query.";

        }

    }

}

$appointments = $conn->query(
    "SELECT * FROM appointments
     ORDER BY doctor ASC, appointment_date ASC, time_slot ASC"
);

?>

<!DOCTYPE html>
<html lang="en">

<head>

    <meta charset="UTF-8">

    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Clinic Appointment Booking</title>

    <link rel="stylesheet" href="style.css">

</head>

<body>

    <h2>Clinic Appointment Booking</h2>

    <p>Allow a patient to request an appointment.</p>

    <form action="" method="POST">

        <div class="form-header">
         Please fill out the form below to request an appointment.
        </div>

        <div class="form-group">

            <label>Patient name</label>

            <input
                type="text"
                name="patient_name"
                value="<?php echo htmlspecialchars($patient_name); ?>"
            >

            <?php if (isset($errors["patient_name"])): ?>

                <p class="error">
                    <?php echo htmlspecialchars($errors["patient_name"]); ?>
                </p>

            <?php endif; ?>

        </div>

        <div class="form-group">

            <label>Email</label>

            <input
                type="email"
                name="email"
                value="<?php echo htmlspecialchars($email); ?>"
            >

            <?php if (isset($errors["email"])): ?>

                <p class="error">
                    <?php echo htmlspecialchars($errors["email"]); ?>
                </p>

            <?php endif; ?>

        </div>

        <div class="form-group">

            <label>Phone</label>

            <input
                type="tel"
                name="phone"
                value="<?php echo htmlspecialchars($phone); ?>"
            >

            <?php if (isset($errors["phone"])): ?>

                <p class="error">
                    <?php echo htmlspecialchars($errors["phone"]); ?>
                </p>

            <?php endif; ?>

        </div>

        <div class="form-group">

            <label>Department</label>

            <select name="department">

                <option value="">Choose an option</option>

                <?php foreach ($departments as $item): ?>

                    <option
                        value="<?php echo htmlspecialchars($item); ?>"
                        <?php if ($department == $item) echo "selected"; ?>
                    >

                        <?php echo htmlspecialchars($item); ?>

                    </option>

                <?php endforeach; ?>

            </select>

            <?php if (isset($errors["department"])): ?>

                <p class="error">
                    <?php echo htmlspecialchars($errors["department"]); ?>
                </p>

            <?php endif; ?>

        </div>

        <div class="form-group">

            <label>Preferred doctor</label>

            <select name="doctor">

                <option value="">Choose an option</option>

                <?php foreach ($doctors as $item): ?>

                    <option
                        value="<?php echo htmlspecialchars($item); ?>"
                        <?php if ($doctor == $item) echo "selected"; ?>
                    >

                        <?php echo htmlspecialchars($item); ?>

                    </option>

                <?php endforeach; ?>

            </select>

            <?php if (isset($errors["doctor"])): ?>

                <p class="error">
                    <?php echo htmlspecialchars($errors["doctor"]); ?>
                </p>

            <?php endif; ?>

        </div>

        <div class="form-group">

            <label>Appointment date</label>

            <input
                type="date"
                name="appointment_date"
                value="<?php echo htmlspecialchars($appointment_date); ?>"
            >

            <?php if (isset($errors["appointment_date"])): ?>

                <p class="error">
                    <?php echo htmlspecialchars($errors["appointment_date"]); ?>
                </p>

            <?php endif; ?>

        </div>

        <div class="form-group">

            <label>Time slot</label>

            <select name="time_slot">

                <option value="">Choose an option</option>

                <?php foreach ($time_slots as $item): ?>

                    <option
                        value="<?php echo htmlspecialchars($item); ?>"
                        <?php if ($time_slot == $item) echo "selected"; ?>
                    >

                        <?php echo htmlspecialchars($item); ?>

                    </option>

                <?php endforeach; ?>

            </select>

            <?php if (isset($errors["time_slot"])): ?>

                <p class="error">
                    <?php echo htmlspecialchars($errors["time_slot"]); ?>
                </p>

            <?php endif; ?>

        </div>

        <div class="form-group">

            <label>Symptoms or reason</label>

            <input
                type="text"
                name="reason"
                value="<?php echo htmlspecialchars($reason); ?>"
            >

            <?php if (isset($errors["reason"])): ?>

                <p class="error">
                    <?php echo htmlspecialchars($errors["reason"]); ?>
                </p>

            <?php endif; ?>

        </div>

        <div class="form-group">

            <label>Consent to data use</label>

            <div>

                <input
                    type="checkbox"
                    name="consent"
                    value="1"
                    <?php if (($_POST["consent"] ?? "") === "1") echo "checked"; ?>
                >

                I agree

            </div>

            <?php if (isset($errors["consent"])): ?>

                <p class="error">
                    <?php echo htmlspecialchars($errors["consent"]); ?>
                </p>

            <?php endif; ?>

        </div>

        <div class="form-buttons">

            <button type="submit">Submit Form</button>

            <button type="reset">Reset</button>

        </div>

    </form>

    <?php if ($success != ""): ?>

        <script>
            alert(<?php echo json_encode($success); ?>);
        </script>

    <?php endif; ?>

    <?php if ($db_error != ""): ?>

        <p class="error">
            <?php echo htmlspecialchars($db_error); ?>
        </p>

    <?php endif; ?>

    <h2>Registered Appointments</h2>

    <table>

        <thead>

            <tr>

                <th>Appointment ID</th>
                <th>Patient Name</th>
                <th>Email</th>
                <th>Phone</th>
                <th>Department</th>
                <th>Doctor</th>
                <th>Date</th>
                <th>Time</th>
                <th>Reason</th>
                <th>Status</th>

            </tr>

        </thead>

        <tbody>

            <?php if ($appointments && $appointments->num_rows > 0): ?>

                <?php while ($row = $appointments->fetch_assoc()): ?>

                    <tr>

                        <td><?php echo htmlspecialchars($row["appointment_id"]); ?></td>

                        <td><?php echo htmlspecialchars($row["patient_name"]); ?></td>

                        <td><?php echo htmlspecialchars($row["email"] ?? ""); ?></td>

                        <td><?php echo htmlspecialchars($row["phone"]); ?></td>

                        <td><?php echo htmlspecialchars($row["department"]); ?></td>

                        <td><?php echo htmlspecialchars($row["doctor"]); ?></td>

                        <td><?php echo htmlspecialchars($row["appointment_date"]); ?></td>

                        <td><?php echo htmlspecialchars($row["time_slot"]); ?></td>

                        <td><?php echo htmlspecialchars($row["reason"]); ?></td>

                        <td><?php echo htmlspecialchars($row["status"]); ?></td>

                    </tr>

                <?php endwhile; ?>

            <?php else: ?>

                <tr>

                    <td colspan="10">
                        No appointments found.
                    </td>

                </tr>

            <?php endif; ?>

        </tbody>

    </table>

    <h2>Appointment Schedule by Doctor</h2>

    <?php

    $schedule = $conn->query(
        "SELECT doctor, patient_name, department,
                appointment_date, time_slot, status
         FROM appointments
         ORDER BY doctor ASC, appointment_date ASC, time_slot ASC"
    );

    $doctor_name = "";

    if ($schedule && $schedule->num_rows > 0):

        while ($row = $schedule->fetch_assoc()):

            if ($doctor_name != $row["doctor"]):

                if ($doctor_name != "") {
                    echo "</tbody></table>";
                }

                $doctor_name = $row["doctor"];

    ?>

                <h3><?php echo htmlspecialchars($doctor_name); ?></h3>

                <table>

                    <tr>

                        <th>Patient</th>
                        <th>Department</th>
                        <th>Appointment Date</th>
                        <th>Time Slot</th>
                        <th>Status</th>

                    </tr>

                    <tbody>

    <?php

            endif;

    ?>

            <tr>

                <td><?php echo htmlspecialchars($row["patient_name"]); ?></td>

                <td><?php echo htmlspecialchars($row["department"]); ?></td>

                <td><?php echo htmlspecialchars($row["appointment_date"]); ?></td>

                <td><?php echo htmlspecialchars($row["time_slot"]); ?></td>

                <td><?php echo htmlspecialchars($row["status"]); ?></td>

            </tr>

    <?php

        endwhile;

        echo "</tbody></table>";

    else:

    ?>

        <p>No appointment schedule available.</p>

    <?php endif; ?>


</body>

</html>

<?php

$conn->close();

?>
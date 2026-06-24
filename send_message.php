<?php
// Check if a message is being sent via POST
$message = '';
if (isset($_POST['message'])) {
    $message = trim($_POST['message']);
    // Save the message to a simple text file
    file_put_contents('message.txt', $message);
    $success = "Message sent successfully!";
}

// Fetch the latest message
$latestMessage = '';
if (file_exists('message.txt')) {
    $latestMessage = file_get_contents('message.txt');
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Send Message to React Native</title>
</head>
<body>
    <h2>Send a Message to Your App</h2>
    <?php if(isset($success)) { echo "<p style='color:green;'>$success</p>"; } ?>
    <form method="POST">
        <input type="text" name="message" placeholder="Enter message" value="" required>
        <button type="submit">Send</button>
    </form>

    <h3>Latest Message</h3>
    <p id="latestMessage"><?php echo htmlspecialchars($latestMessage); ?></p>
</body>
</html>

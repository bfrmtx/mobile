<!DOCTYPE html>
<html lang="en">

<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>PHP Detect and Reload</title>
  <style>
    body {
      font-family: Arial, sans-serif;
      text-align: center;
      margin-top: 50px;
    }

    button {
      padding: 10px 20px;
      font-size: 16px;
      cursor: pointer;
    }

    #message {
      margin-top: 20px;
      font-weight: bold;
      color: green;
    }
  </style>
</head>

<body>
  <h1>Detection Portal</h1>
  <!-- The form submits to itself via POST when clicked -->
  <form method="post" action="">
    <button type="submit" name="detect_btn" id="detectBtn">Detect</button>
  </form>
  <div id="message"></div>

  <?php
  // Check if the "Detect" button was pressed
  if (isset($_POST['detect_btn'])) {
    // Output JavaScript to handle the 5-second delay and reload
    echo "<script>
			let seconds = 5;
			const messageDiv = document.getElementById('message');
			const button = document.getElementById('detectBtn');

			// Disable button to prevent double clicks
			button.disabled = true;

			// Countdown timer interval
			const interval = setInterval(() => {
				messageDiv.innerHTML = 'Detection triggered! Reloading in ' + seconds + ' seconds...';
				seconds--;

				if (seconds < 0) {
					clearInterval(interval);
					window.location.href = window.location.pathname; // Reloads the page cleanly
				}
			}, 1000);
		</script>";
  }
  ?>
</body>

</html>
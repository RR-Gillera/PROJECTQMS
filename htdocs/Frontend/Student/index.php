<?php
// Redirect any direct access to this folder's index to the site's main index
$target = '/index.php';

// Prefer HTTP redirect
header('Location: ' . $target, true, 302);
exit;
?>
<!DOCTYPE html>
<html lang="en">
<head>
	<meta charset="utf-8" />
	<meta http-equiv="refresh" content="0; url=<?php echo htmlspecialchars($target, ENT_QUOTES, 'UTF-8'); ?>">
	<title>Redirecting…</title>
	<script>
		window.location.replace('<?php echo addslashes($target); ?>');
	</script>
</head>
<body>
	<p>If you are not redirected automatically, <a href="<?php echo htmlspecialchars($target, ENT_QUOTES, 'UTF-8'); ?>">click here</a>.</p>
</body>
</html>

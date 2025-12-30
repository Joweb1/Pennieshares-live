<?php
require_once __DIR__ . '/src/init.php';
check_auth();

if (!isset($_SESSION['paystack_data'])) {
    header("Location: /add_money");
    exit;
}

$paystack_data = $_SESSION['paystack_data'];
unset($_SESSION['paystack_data']);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Pay with Paystack</title>
    <script src="https://js.paystack.co/v1/inline.js"></script>
</head>
<body>
    <script>
        const paystack_data = <?php echo json_encode($paystack_data); ?>;
        
        const handler = PaystackPop.setup({
            key: paystack_data.key,
            email: paystack_data.email,
            amount: paystack_data.amount,
            currency: paystack_data.currency,
            ref: paystack_data.ref,
            callback: function(response) {
                window.location = paystack_data.callback_url + '?reference=' + response.reference;
            },
            onClose: function() {
                alert('Payment was not completed.');
                window.location = '/add_money';
            }
        });
        handler.openIframe();
    </script>
</body>
</html>

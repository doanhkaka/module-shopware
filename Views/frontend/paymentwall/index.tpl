<html>
<head>
    <title>Payment via Paymentwall</title>
    <meta charset="UTF-8" />
    <meta name="author" content="Paymentwall" />
    <link rel="shortcut icon" href="https://api.paymentwall.com/images/paymentwall.ico">
    <script type="text/javascript">
        var xhttp = new XMLHttpRequest();
        setInterval(function () {
            xhttp.onreadystatechange = function () {
                if (xhttp.readyState == 4 && xhttp.status == 200) {
                    if (0 != xhttp.responseText) {
                        window.location.href = 'checkout/finish';
                    }
                }
            };
            xhttp.open("GET", "paymentwall/redirect/?orderId={$orderId}", true);
            xhttp.send();
        }, 10000);
    </script>
</head>
<body>
    {$iframe}
</body>
</html>



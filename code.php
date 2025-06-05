<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>vCard QR Code Generator</title>
    <script src="https://cdn.jsdelivr.net/npm/qrcode@1.4.4/build/qrcode.min.js"></script>
    <style>
        .card-container {
            display: flex;
            justify-content: space-around;
            margin-top: 20px;
            gap: 40px; /* Increased gap between the cards */
        }
        .card {
            border: 1px solid #ccc;
            padding: 20px;
            text-align: center;
            width: 200px;
        }
        img {
            width: 150px;
            height: 150px;
        }
    </style>
</head>
<body>
    <h1>vCard QR Code Generator</h1>

    <!-- Container for displaying the two cards -->
    <div id="qrcode-container" class="card-container"></div>

    <script>
        // Define the vCard data for two contacts
        const vCard1 = `
BEGIN:VCARD
VERSION:3.0
FN:John Doe
TEL:+1234567890
EMAIL:john.doe@example.com
END:VCARD
`;

        const vCard2 = `
BEGIN:VCARD
VERSION:3.0
FN:Jane Smith
TEL:+0987654321
EMAIL:jane.smith@example.com
END:VCARD
`;

        // Generate the QR code for the first contact
        QRCode.toDataURL(vCard1, { 
            width: 300,           // Adjust size for better readability
            margin: 2,            // Add margin
            errorCorrectionLevel: 'H'  // High error correction for better readability
        }, function (error, url) {
            if (error) {
                console.error(error);
                return;
            }

            // Create an <img> element and set its src attribute to the generated QR code data URL
            const img1 = document.createElement('img');
            img1.src = url;

            // Create a container for the first contact's QR code
            const card1 = document.createElement('div');
            card1.classList.add('card');
            card1.innerHTML = "<h3>John Doe</h3><p>john.doe@example.com</p><p>+1234567890</p>";
            card1.appendChild(img1);

            // Append the first card to the container
            document.getElementById('qrcode-container').appendChild(card1);
        });

        // Generate the QR code for the second contact
        QRCode.toDataURL(vCard2, { 
            width: 300,           // Adjust size for better readability
            margin: 1,            // Add margin
            errorCorrectionLevel: 'H'  // High error correction for better readability
        }, function (error, url) {
            if (error) {
                console.error(error);
                return;
            }

            // Create an <img> element and set its src attribute to the generated QR code data URL
            const img2 = document.createElement('img');
            img2.src = url;

            // Create a container for the second contact's QR code
            const card2 = document.createElement('div');
            card2.classList.add('card');
            card2.innerHTML = "<h3>Jane Smith</h3><p>jane.smith@example.com</p><p>+0987654321</p>";
            card2.appendChild(img2);

            // Append the second card to the container
            document.getElementById('qrcode-container').appendChild(card2);
        });
    </script>
</body>
</html>

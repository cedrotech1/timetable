<?php
include("connection.php");
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="utf-8">
    <meta content="width=device-width, initial-scale=1.0" name="viewport">

    <title>UR-HUYE</title>

    <!-- Favicons -->
    <link href="./icon1.png" rel="icon">
    <link href="./icon1.png" rel="apple-touch-icon">

    <!-- Google Fonts -->
    <link href="https://fonts.gstatic.com" rel="preconnect">
    <link href="https://fonts.googleapis.com/css?family=Open+Sans:300,300i,400,400i,600,600i,700,700i|Nunito:300,300i,400,400i,600,600i,700,700i|Poppins:300,300i,400,400i,500,500i,600,600i,700,700i"
        rel="stylesheet">

    <!-- Vendor CSS Files -->
    <link href="./Dashboard/assets/vendor/bootstrap/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.datatables.net/1.13.6/css/jquery.dataTables.min.css" rel="stylesheet">

    <!-- Include SheetJS library -->
    <script src="https://cdnjs.cloudflare.com/ajax/libs/xlsx/0.17.1/xlsx.full.min.js"></script>

</head>

<body>
    <section class="section dashboard">
        <div class="row">
            <div class="col-lg-12">
                <div class="card">
                    <div class="card-body">
                        <br>

                        <div class="row">
                            <div class="col-6">
                                <h5 class="card-title" style="font-size:15px">LIST OF REJECTED CARDS</h5>
                            </div>
                            <div class="col-6">
                                <?php
                                session_start();
                                if (isset($_SESSION['role']) && $_SESSION['role'] === 'admin') { // Adjust the 'admin' to the appropriate role
                                    ?>
                                    <button class="btn btn-success btn-sm" id="exportButton">Export to Excel</button>
                                    <?php
                                }
                                ?>
                            </div>
                        </div>

                        <div class="col-md-12 table-responsive">
                            <!-- Export Button -->

                            <table class="table datatable table-striped table-hover">
                                <thead>
                                    <tr>
                                        <th><b>#</b></th>
                                        <th><b>Reg Number</b></th>
                                        <th><b>Names</b></th>
                                        <th><b>College</b></th>
                                        <th><b>School</b></th>
                                        <th><b>Program</b></th>
                                        <th><b>Year</b></th>
                                        <th style="display:none;"><b>Image Path</b></th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php
                                    $ok = mysqli_query($connection, "SELECT * FROM info
WHERE names REGEXP '[^A-Za-z0-9 ]' 
AND names NOT LIKE '%\'%'");
                                    $i = 0;
                                    while ($row = mysqli_fetch_array($ok)) {
                                        $i++;
                                        ?>
                                        <tr data-image="<?php echo $row['picture']; ?>">
                                            <td><?php echo $i; ?></td>
                                            <td><?php echo $row['regnumber']; ?></td>
                                            <td><?php echo $row['names']; ?></td>
                                            <td><?php echo $row['college']; ?></td>
                                            <td><?php echo $row['school']; ?></td>
                                            <td><?php echo $row['program']; ?></td>
                                            <td><?php echo $row['yearofstudy']; ?></td>
                                            <td style="display:none;"><?php echo $row['picture']; ?></td>
                                        </tr>
                                        <?php
                                    }
                                    ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Modal Structure -->
    <div class="modal fade" id="imageModal" tabindex="-1" aria-labelledby="imageModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="imageModalLabel">Student Image</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body text-center">
                    <img id="modalImage" src="" alt="Student Image" class="img-fluid">
                </div>
            </div>
        </div>
    </div>

    <!-- Vendor JS Files -->
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.datatables.net/1.13.6/js/jquery.dataTables.min.js"></script>
    <script src="./Dashboard/assets/vendor/bootstrap/js/bootstrap.bundle.min.js"></script>

    <script>
        $(document).ready(function () {
            // Initialize the DataTable with "All" option
            $('.datatable').DataTable({
                pageLength: 10, // Set the default number of entries per page (optional)
                lengthMenu: [
                    [10, 25, 50, 100, -1], // The options for the number of entries per page
                    [10, 25, 50, 100, "All"] // Display text for each option
                ]
            });

            // Handle row click event for image modal
            $('.datatable tbody').on('click', 'tr', function () {
                const imagePath = $(this).data('image'); // Get the image path from the data attribute

                if (imagePath) {
                    $('#modalImage').attr('src', `./Students/${imagePath}`); // Set the modal image source
                    $('#imageModal').modal('show'); // Show the modal
                } else {
                    alert("No image available for this student.");
                }
            });

            // Handle the export to Excel functionality for selected columns (regnumber and names)
         // Handle the export to Excel functionality for all columns
$("#exportButton").click(function () {
    var table = document.querySelector("table"); // Get the table element
    var rows = table.querySelectorAll("tbody tr"); // Select all rows in the table

    // Create a temporary array to store the data
    var data = [];

    // Add header row
    var headers = [];
    table.querySelectorAll("th").forEach(function (header) {
        headers.push(header.textContent.trim()); // Get each header's text and add to headers array
    });
    data.push(headers); // Add headers to data array

    // Loop through all rows and get all columns
    rows.forEach(function (row) {
        var rowData = [];
        row.querySelectorAll("td").forEach(function (cell) {
            rowData.push(cell.textContent.trim()); // Add each cell's content to rowData array
        });
        data.push(rowData); // Add row data to data array
    });

    // Create a workbook from the gathered data
    var workbook = XLSX.utils.book_new();
    var worksheet = XLSX.utils.aoa_to_sheet(data); // Convert the array of arrays to a worksheet
    XLSX.utils.book_append_sheet(workbook, worksheet, "Rejected Cards");

    // Download the file
    XLSX.writeFile(workbook, "rejected_cards.xlsx");
});

        });
    </script>

</body>

</html>

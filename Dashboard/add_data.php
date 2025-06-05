<?php
include('connection.php');

include ('./includes/auth.php');
checkUserRole(['information_modifier']);


// Function to check if there's data in the system
function checkExistingData($connection) {
    $query = "SELECT COUNT(*) as count FROM info"; // Adjust table name if necessary
    $result = mysqli_query($connection, $query);
    
    if ($result) {
        $row = mysqli_fetch_assoc($result);
        return $row['count'] > 0; // Return true if data exists, false otherwise
    } else {
        return false; // Handle the case where the query fails
    }
}

$existingData = checkExistingData($connection); // Check if data exists
?>

<!DOCTYPE html>
<html lang="en">

<head>
  <meta charset="utf-8">
  <meta content="width=device-width, initial-scale=1.0" name="viewport">

  <title>UR-HOSTELS</title>
  <meta content="" name="description">
  <meta content="" name="keywords">

  <!-- Favicons -->
  <link href="assets/img/icon1.png" rel="icon">
  <link href="assets/img/icon1.png" rel="apple-touch-icon">

  <!-- Google Fonts -->
  <link href="https://fonts.gstatic.com" rel="preconnect">
  <link href="https://fonts.googleapis.com/css?family=Open+Sans:300,300i,400,400i,600,600i,700,700i|Nunito:300,300i,400,400i,600,600i,700,700i|Poppins:300,300i,400,400i,500,500i,600,600i,700,700i" rel="stylesheet">

  <!-- Vendor CSS Files -->
  <link href="assets/vendor/bootstrap/css/bootstrap.min.css" rel="stylesheet">
  <link href="assets/vendor/bootstrap-icons/bootstrap-icons.css" rel="stylesheet">
  <link href="assets/vendor/boxicons/css/boxicons.min.css" rel="stylesheet">
  <link href="assets/vendor/quill/quill.snow.css" rel="stylesheet">
  <link href="assets/vendor/quill/quill.bubble.css" rel="stylesheet">
  <link href="assets/vendor/remixicon/remixicon.css" rel="stylesheet">
  <link href="assets/vendor/simple-datatables/style.css" rel="stylesheet">

  <!-- Template Main CSS File -->
  <link href="assets/css/style.css" rel="stylesheet">

  <!-- XLSX and PapaParse libraries -->
  <script src="https://cdnjs.cloudflare.com/ajax/libs/xlsx/0.18.5/xlsx.full.min.js"></script>
  <script src="https://cdnjs.cloudflare.com/ajax/libs/PapaParse/5.3.0/papaparse.min.js"></script>

</head>

<body>

<?php  
include("./includes/header.php");
include("./includes/menu.php");
?>

<main id="main" class="main">

  <div class="pagetitle">
      <h1>Data</h1>
      <nav>
        <ol class="breadcrumb">
          <li class="breadcrumb-item"><a href="index.php">Home</a></li>
          <li class="breadcrumb-item">data</li>
          <li class="breadcrumb-item active">upload</li>
        </ol>
      </nav>
  </div><!-- End Page Title -->

  <section class="section dashboard">
    <div class="row">
      <div class="col-lg-6">
        <div class="row">
          <div class="card">
          <div class="card-body">
              <br>
             

              <?php if ($existingData=1) : ?><br/>
                <h5 class="card-title">UPLOAD STUDENT INFORMATION FORM</h5>
                <br>
                <div class="col-md-12">
                <div class="form-floating">
                  <input class="form-control" type="file" id="dataFile" accept=".xls,.xlsx,.csv" />
                  <label for="floatingName">DATA</label>
                </div>
              </div>
              <br>
              <div class="text-center">
                <button type="submit" id="uploadButton" name="saveproduct" class="btn btn-primary" 
                  >Save Data</button>
                <button type="reset" class="btn btn-secondary">Reset</button>
              </div>
              <?php endif; ?>

          
              <!-- <?php if ($existingData) : ?><br/>
              <div class="alert alert-warning" role="alert">
                Data already exists in the system.
              </div>
              <?php endif; ?> -->
            </div>
          </div>
        </div>
      </div><!-- End Left side columns -->
      <!-- template for data and file upload -->
      <div class="col-lg-6">
        <div class="card">
          <div class="card-body">
            <h5 class="card-title">Template & Instructions</h5>
            
            <!-- Download Template Section -->
            <div class="mb-4">
              <h6 class="fw-bold">Download Template</h6>
              <p class="text-muted">Use our  template to ensure your data is correctly structured or make sure header of each column is correct as in that template </p>
              <button onclick="downloadTemplate()" class="btn btn-primary">
                <i class="bi bi-download me-1"></i> Download Template
              </button>
            </div>

            <!-- Instructions Section -->
            <div class="mb-4">
              <h6 class="fw-bold">Instructions for Data Upload</h6>
              <div class="alert alert-info">
                <h6 class="alert-heading">Important Notes:</h6>
                <ol class="mb-0">
                  <li>All fields marked with * are required</li>
                  <li>File must be in Excel (.xlsx) or CSV format</li>
                  <!-- <li>Maximum file size: 5MB</li> -->
                  <li>Do not modify the header row OR make sure header of each column is correct as in that template</li>
                  <li>Save Excel files as CSV before uploading that is good plactice </li>
               
                </ol>
              </div>
            </div>

            <!-- Field Requirements -->
            <div class="mb-4">
              <h6 class="fw-bold">Field Requirements</h6>
              <div class="table-responsive">
                <table class="table table-sm">
                  <thead>
                    <tr>
                      <th>Field</th>
                      <th>Requirements</th>
                    </tr>
                  </thead>
                  <tbody>
                    <tr>
                      <td>Registration Number*</td>
                      <td>Unique student ID (e.g., 2023/1234)</td>
                    </tr>
                    <tr>
                      <td>Campus*</td>
                      <td>Must match existing campus name exactly</td>
                    </tr>
                    <tr>
                      <td>College*</td>
                      <td>Student's college name</td>
                    </tr>
                    <tr>
                      <td>Sirname*</td>
                      <td>Student's sirname</td>
                    </tr>
                    <tr>
                      <td>Lastname*</td>
                      <td>Student's lastname</td>
                    </tr>
                    <tr>
                      <td>School*</td>
                      <td>Student's school name</td>
                    </tr>
                    <tr>
                      <td>Program*</td>
                      <td>Student's program name</td>
                    </tr>
                    <tr>
                      <td>Year of Study*</td>
                      <td>Current year (1-5)</td>
                    </tr>
                    <tr>
                      <td>Email*</td>
                      <td>Valid email address</td>
                    </tr>
                    <tr>
                      <td>Gender*</td>
                      <td>Male/Female or M/F</td>
                    </tr>
                    <tr>
                      <td>National ID*</td>
                      <td>Valid national ID number</td>
                    </tr>
                    <tr>
                      <td>Phone*</td>
                      <td>10-digit number starting with 0</td>
                    </tr>
                  </tbody>
                </table>
              </div>
            </div>

            <!-- Common Issues -->
            <div class="mb-4">
              <h6 class="fw-bold">Common Issues & Solutions</h6>
              <div class="alert alert-warning">
                <ul class="mb-0">
                  <li>Ensure all required fields are filled</li>
                  <li>Check that campus names match exactly</li>
                  <li>Registration numbers must be unique means no two students can have the same registration number</li>
                </ul>
              </div>
            </div>

            <!-- Support -->
            <div>
              <h6 class="fw-bold">Need Help?</h6>
              <p class="text-muted">If you encounter any issues, please contact the system administrator or refer to the user manual.</p>
            </div>
          </div>
        </div>
      </div>
    </div>
  </section>

</main><!-- End #main -->

<script>
  document.getElementById('uploadButton').addEventListener('click', function () {
      uploadFile();
  });

  function uploadFile() {
      var fileInput = document.getElementById('dataFile');
      var file = fileInput.files[0];
      var uploadButton = document.getElementById('uploadButton');

      if (!file) {
          alert("Please select a file.");
          return;
      }

      // Disable button and show loading state
      uploadButton.disabled = true;
      uploadButton.innerHTML = "Loading...";

      var fileExtension = file.name.split('.').pop().toLowerCase();
      if (fileExtension === 'xls' || fileExtension === 'xlsx') {
          readExcel(file);
      } else if (fileExtension === 'csv') {
          readCSV(file);
      } else {
          alert("Unsupported file format. Please upload an Excel or CSV file.");
          uploadButton.disabled = false;  // Re-enable button if error
          uploadButton.innerHTML = "Save Data";
      }
  }

  // Function to read Excel files
  function readExcel(file) {
      var reader = new FileReader();

      reader.onload = function (e) {
          var data = new Uint8Array(e.target.result);
          var workbook = XLSX.read(data, { type: 'array' });
          var firstSheet = workbook.Sheets[workbook.SheetNames[0]];
          
          // Convert to array with empty string for empty cells
          var excelRows = XLSX.utils.sheet_to_json(firstSheet, { 
              header: 1,
              defval: '',
              blankrows: false
          });
          
          // Filter out completely empty rows
          excelRows = excelRows.filter(row => row.some(cell => cell !== ''));
          
          // Send data to the server
          uploadToServer(excelRows);
      };

      reader.readAsArrayBuffer(file);
  }

  // Function to read CSV files
  function readCSV(file) {
      Papa.parse(file, {
          complete: function (results) {
              // Filter out completely empty rows
              var filteredData = results.data.filter(row => 
                  row.some(cell => cell !== '' && cell !== null)
              );
              
              // Send data to the server
              uploadToServer(filteredData);
          },
          skipEmptyLines: true,
          transform: function(value) {
              return value.trim();
          }
      });
  }

  // Function to upload data to the server
  function uploadToServer(dataRows) {
      fetch('welfare_upload_excel.php', {
          method: 'POST',
          headers: {
              'Content-Type': 'application/json'
          },
          body: JSON.stringify({ data: dataRows })
      })
      .then(response => {
          if (!response.ok) {
              throw new Error('Network response was not ok');
          }
          return response.json();
      })
      .then(response => {
          if (response.status === 'error') {
              throw new Error(response.message);
          }
          showResultsModal(response);
      })
      .catch(error => {
          console.error('Error:', error);
          showResultsModal({
              status: 'error',
              message: error.message || 'An error occurred while processing the file. Please try again.',
              data: {
                  errors: [error.message],
                  success: []
              }
          });
      })
      .finally(() => {
          // Re-enable the button after processing
          var uploadButton = document.getElementById('uploadButton');
          uploadButton.disabled = false;
          uploadButton.innerHTML = "Save Data";
      });
  }

  // Function to display results in a modal
  function showResultsModal(response) {
      // Create modal HTML
      var modalHtml = `
          <div class="modal fade" id="resultsModal" tabindex="-1">
              <div class="modal-dialog modal-lg">
                  <div class="modal-content">
                      <div class="modal-header">
                          <h5 class="modal-title">Upload Results</h5>
                          <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                      </div>
                      <div class="modal-body">
                          <div class="alert alert-${response.status === 'success' ? 'success' : 
                                               response.status === 'partial' ? 'warning' : 'danger'}">
                              ${response.message}
                          </div>
                          ${response.data.errors.length > 0 ? `
                              <div class="mt-3">
                                  <h6>Errors:</h6>
                                  <div class="table-responsive">
                                      <table class="table table-sm table-bordered">
                                          <thead class="table-light">
                                              <tr>
                                                  <th>Row</th>
                                                  <th>Error</th>
                                              </tr>
                                          </thead>
                                          <tbody>
                                              ${response.data.errors.map(error => {
                                                  const match = error.match(/Row (\d+): (.*)/);
                                                  return `
                                                      <tr>
                                                          <td>${match ? match[1] : 'N/A'}</td>
                                                          <td>${match ? match[2] : error}</td>
                                                      </tr>
                                                  `;
                                              }).join('')}
                                          </tbody>
                                      </table>
                                  </div>
                              </div>
                          ` : ''}
                          ${response.data.success.length > 0 ? `
                              <div class="mt-3">
                                  <h6>Successful Uploads:</h6>
                                  <div class="table-responsive">
                                      <table class="table table-sm table-bordered">
                                          <thead class="table-light">
                                              <tr>
                                                  <th>Row</th>
                                                  <th>Details</th>
                                              </tr>
                                          </thead>
                                          <tbody>
                                              ${response.data.success.map(success => {
                                                  const match = success.match(/Row (\d+): (.*)/);
                                                  return `
                                                      <tr>
                                                          <td>${match ? match[1] : 'N/A'}</td>
                                                          <td>${match ? match[2] : success}</td>
                                                      </tr>
                                                  `;
                                              }).join('')}
                                          </tbody>
                                      </table>
                                  </div>
                              </div>
                          ` : ''}
                      </div>
                      <div class="modal-footer">
                          <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                          ${response.status === 'success' ? `
                              <button type="button" class="btn btn-primary" onclick="window.location.reload()">Refresh Page</button>
                          ` : ''}
                      </div>
                  </div>
              </div>
          </div>
      `;

      // Add modal to body
      document.body.insertAdjacentHTML('beforeend', modalHtml);

      // Show modal
      var modal = new bootstrap.Modal(document.getElementById('resultsModal'));
      modal.show();

      // Remove modal from DOM after it's hidden
      document.getElementById('resultsModal').addEventListener('hidden.bs.modal', function () {
          this.remove();
      });
  }

  function downloadTemplate() {
      // Define the CSV content
      const headers = [
          'regnumber',
          'campus',
          'college',
          'sirname',
          'lastname',
          'school',
          'program',
          'yearofstudy',
          'email',
          'gender',
          'nid',
          'phone'
      ];

      // Example data
      const exampleData = [
          ['201018991', 'huye', 'College of Science', 'John', 'Doe', 'School of Engineering', 'Computer Science', '1', 'john.doe@example.com', 'Male', '1234567890123456', '781234567'],
          ['201018992', 'huye', 'College of Science', 'Jane', 'Smith', 'School of Engineering', 'Information Technology', '2', 'jane.smith@example.com', 'Female', '1234567890123457', '781234568']
      ];

      // Convert to CSV format
      let csvContent = headers.join(',') + '\n';
      exampleData.forEach(row => {
          csvContent += row.join(',') + '\n';
      });

      // Create and trigger download
      const blob = new Blob([csvContent], { type: 'text/csv;charset=utf-8;' });
      const link = document.createElement('a');
      const url = URL.createObjectURL(blob);
      link.setAttribute('href', url);
      link.setAttribute('download', 'student_data_template.csv');
      link.style.visibility = 'hidden';
      document.body.appendChild(link);
      link.click();
      document.body.removeChild(link);
  }
</script>

<?php  
include("./includes/footer.php");
?>

<a href="#" class="back-to-top d-flex align-items-center justify-content-center"><i class="bi bi-arrow-up-short"></i></a>

<!-- Vendor JS Files -->
<script src="assets/vendor/apexcharts/apexcharts.min.js"></script>
<script src="assets/vendor/bootstrap/js/bootstrap.bundle.min.js"></script>
<script src="assets/vendor/chart.js/chart.umd.js"></script>
<script src="assets/vendor/echarts/echarts.min.js"></script>
<script src="assets/vendor/quill/quill.min.js"></script>
<script src="assets/vendor/simple-datatables/simple-datatables.js"></script>
<script src="assets/vendor/tinymce/tinymce.min.js"></script>
<script src="assets/vendor/php-email-form/validate.js"></script>

<!-- Template Main JS File -->
<script src="assets/js/main.js"></script>

</body>
</html>

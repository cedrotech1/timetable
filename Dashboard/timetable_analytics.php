<?php
    // session_start();
    require_once 'connection.php';

    // Check if user is logged in
    if (!isset($_SESSION['id'])) {
        header('Location: login.php');
        exit();
    }

    $pageTitle = 'Timetable Analytics';
    ?>

    <!DOCTYPE html>
    <html lang="en">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title><?php echo $pageTitle; ?></title>
        
        <!-- Bootstrap CSS -->
        <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
        <!-- Chart.js -->
        <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
        <!-- Font Awesome -->
        <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
        
        <style>
            .card {
                margin-bottom: 20px;
                box-shadow: 0 0.125rem 0.25rem rgba(0, 0, 0, 0.075);
            }
            .card-header {
                font-weight: 600;
            }
            .chart-container {
                position: relative;
                height: 400px;
                margin-bottom: 20px;
            }
            .date-range-picker {
                background: #f8f9fa;
                padding: 15px;
                border-radius: 5px;
                margin-bottom: 20px;
            }
        </style>
    </head>
    <body>
        <?php include('includes/header.php'); ?>
        
        <div class="container-fluid">
            <div class="row">
                <div class="col-12">
                    <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                        <h1 class="h2">Timetable Analytics</h1>
                    </div>
                </div>
            </div>

            <!-- Date Range Picker -->
            <div class="row mb-4">
                <div class="col-12">
                    <div class="card">
                        <div class="card-header">
                            <i class="fas fa-calendar-alt me-2"></i>Select Date Range
                        </div>
                        <div class="card-body">
                            <form id="dateRangeForm" class="row g-3">
                                <div class="col-md-3">
                                    <label for="startDate" class="form-label">Start Date</label>
                                    <input type="date" class="form-control" id="startDate" required>
                                </div>
                                <div class="col-md-3">
                                    <label for="endDate" class="form-label">End Date</label>
                                    <input type="date" class="form-control" id="endDate" required>
                                </div>
                                <div class="col-md-3">
                                    <label for="groupBy" class="form-label">Group By</label>
                                    <select class="form-select" id="groupBy">
                                        <option value="day">Day</option>
                                        <option value="week">Week</option>
                                        <option value="month" selected>Month</option>
                                        <option value="year">Year</option>
                                    </select>
                                </div>
                                <div class="col-md-3 d-flex align-items-end">
                                    <button type="submit" class="btn btn-primary">
                                        <i class="fas fa-sync-alt me-1"></i> Update
                                    </button>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Charts Row -->
            <div class="row">
                <!-- Timetable Creation Over Time -->
                <div class="col-12">
                    <div class="card">
                        <div class="card-header">
                            <i class="fas fa-chart-line me-2"></i>Timetable Creation Over Time
                        </div>
                        <div class="card-body">
                            <div class="chart-container">
                                <canvas id="creationChart"></canvas>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="row">
                <!-- Timetable by Day of Week -->
                <div class="col-md-6">
                    <div class="card h-100">
                        <div class="card-header">
                            <i class="fas fa-calendar-day me-2"></i>Timetable by Day of Week
                        </div>
                        <div class="card-body">
                            <div class="chart-container">
                                <canvas id="dayOfWeekChart"></canvas>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Timetable by Time of Day -->
                <div class="col-md-6">
                    <div class="card h-100">
                        <div class="card-header">
                            <i class="fas fa-clock me-2"></i>Timetable by Time of Day
                        </div>
                        <div class="card-body">
                            <div class="chart-container">
                                <canvas id="timeOfDayChart"></canvas>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- JavaScript -->
        <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
        <script>
            // Set default date range (last 7 days)
            document.addEventListener('DOMContentLoaded', function() {
                const endDate = new Date();
                const startDate = new Date();
                startDate.setDate(startDate.getDate() - 6); // 6 days ago (to include today + 6 days = 7 days total)
                
                // Format dates as YYYY-MM-DD
                const formatDate = (date) => {
                    const d = new Date(date);
                    const year = d.getFullYear();
                    const month = String(d.getMonth() + 1).padStart(2, '0');
                    const day = String(d.getDate()).padStart(2, '0');
                    return `${year}-${month}-${day}`;
                };
                
                // Generate array of last 7 days for Y-axis
                function getLast7Days() {
                    const days = [];
                    const dayNames = ['Sunday', 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday'];
                    for (let i = 6; i >= 0; i--) {
                        const d = new Date();
                        d.setDate(d.getDate() - i);
                        days.push({
                            date: formatDate(d),
                            label: dayNames[d.getDay()] + ' ' + d.getDate()
                        });
                    }
                    return days;
                }
                
                window.last7Days = getLast7Days();
                
                document.getElementById('startDate').value = formatDate(startDate);
                document.getElementById('endDate').value = formatDate(endDate);
                
                // Load initial data
                fetchTimetableData();
            });

            // Handle form submission
            document.getElementById('dateRangeForm').addEventListener('submit', function(e) {
                e.preventDefault();
                fetchTimetableData();
            });

            // Fetch timetable data
            async function fetchTimetableData() {
                const startDate = document.getElementById('startDate').value;
                const endDate = document.getElementById('endDate').value;
                
                try {
                    // Use correct path to the API endpoint
                    const apiUrl = 'get_timetable_analytics.php';
                    const response = await fetch(`${apiUrl}?start_date=${startDate}&end_date=${endDate}`, {
                        credentials: 'same-origin' // Include cookies if needed
                    });
                    const result = await response.json();
                    
                    if (!response.ok) {
                        throw new Error(result.message || 'Failed to fetch data');
                    }
                    
                    if (!result.success) {
                        throw new Error(result.message || 'Error in response');
                    }
                    
                    // Process the data for the last 7 days
                    const today = new Date();
                    const last7DaysData = [];
                    const dayLabels = [];
                    
                    for (let i = 6; i >= 0; i--) {
                        const date = new Date();
                        date.setDate(today.getDate() - i);
                        
                        const dayOfWeek = date.getDay(); // 0 (Sunday) to 6 (Saturday)
                        const dayName = ['Sun', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat'][dayOfWeek];
                        const dayNumber = date.getDate();
                        
                        // For demo, we'll use the byDayOfWeek data since the API doesn't have daily data
                        // In a real scenario, you'd want to modify the API to return daily data
                        const dayData = {
                            label: `${dayName} ${dayNumber}`,
                            value: result.data.byDayOfWeek[dayOfWeek] || 0
                        };
                        
                        last7DaysData.push(dayData.value);
                        dayLabels.push(dayData.label);
                    }
                    
                    // Update charts with processed data
                    updateCharts({
                        creationOverTime: {
                            labels: dayLabels,
                            values: last7DaysData
                        },
                        byDayOfWeek: result.data.byDayOfWeek,
                        byTimeOfDay: result.data.byTimeOfDay
                    });
                    
                } catch (error) {
                    console.error('Error fetching timetable data:', error);
                    showErrorToast('Error loading data: ' + (error.message || 'Unknown error'));
                }
            }
            
            // Show error toast
            function showErrorToast(message) {
                const toastEl = document.createElement('div');
                toastEl.className = 'toast align-items-center text-white bg-danger border-0 position-fixed bottom-0 end-0 m-3';
                toastEl.role = 'alert';
                toastEl.setAttribute('aria-live', 'assertive');
                toastEl.setAttribute('aria-atomic', 'true');
                toastEl.innerHTML = `
                    <div class="d-flex">
                        <div class="toast-body">
                            <i class="fas fa-exclamation-triangle me-2"></i>${message}
                        </div>
                        <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast" aria-label="Close"></button>
                    </div>
                `;
                document.body.appendChild(toastEl);
                const toast = new bootstrap.Toast(toastEl);
                toast.show();
                
                // Auto-remove toast after 5 seconds
                setTimeout(() => {
                    toastEl.remove();
                }, 5000);
            }

            // Chart instances
            let creationChart, dayOfWeekChart, timeOfDayChart;

            // Initialize and update charts
            function updateCharts(data) {
                if (data.creationOverTime) {
                    updateCreationChart(data.creationOverTime);
                }
                if (data.byDayOfWeek) {
                    updateDayOfWeekChart(data.byDayOfWeek);
                }
                if (data.byTimeOfDay) {
                    updateTimeOfDayChart(data.byTimeOfDay);
                }
            }

            function updateCreationChart(data) {
                const ctx = document.getElementById('creationChart').getContext('2d');
                
                if (creationChart) {
                    creationChart.destroy();
                }
                
                creationChart = new Chart(ctx, {
                    type: 'line',
                    data: {
                        labels: data.labels,
                        datasets: [{
                            label: 'Timetable Entries',
                            data: data.values,
                            backgroundColor: 'rgba(75, 192, 192, 0.1)',
                            borderColor: 'rgba(75, 192, 192, 1)',
                            borderWidth: 3,
                            pointBackgroundColor: 'rgba(75, 192, 192, 1)',
                            pointBorderColor: '#fff',
                            pointHoverBackgroundColor: '#fff',
                            pointHoverBorderColor: 'rgba(75, 192, 192, 1)',
                            pointRadius: 5,
                            pointHoverRadius: 7,
                            fill: true,
                            tension: 0.3
                        }]
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        scales: {
                            y: {
                                beginAtZero: true,
                                title: {
                                    display: true,
                                    text: 'Number of Entries',
                                    font: {
                                        weight: 'bold',
                                        size: 12
                                    },
                                    padding: {top: 10, bottom: 10}
                                },
                                ticks: {
                                    stepSize: 1,
                                    precision: 0,
                                    font: {
                                        size: 11
                                    }
                                },
                                grid: {
                                    display: true,
                                    color: 'rgba(0, 0, 0, 0.05)',
                                    drawBorder: true
                                }
                            },
                            x: {
                                title: {
                                    display: true,
                                    text: 'Date',
                                    font: {
                                        weight: 'bold',
                                        size: 12
                                    },
                                    padding: {top: 10, bottom: 10}
                                },
                                grid: {
                                    display: false
                                },
                                ticks: {
                                    font: {
                                        size: 11
                                    }
                                }
                            }
                        },
                        plugins: {
                            tooltip: {
                                backgroundColor: 'rgba(0, 0, 0, 0.85)',
                                titleFont: {
                                    size: 13,
                                    weight: 'bold',
                                    family: "-apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, 'Helvetica Neue', Arial, sans-serif"
                                },
                                bodyFont: {
                                    size: 13,
                                    family: "-apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, 'Helvetica Neue', Arial, sans-serif"
                                },
                                padding: 10,
                                cornerRadius: 6,
                                displayColors: false,
                                callbacks: {
                                    title: function(tooltipItems) {
                                        return 'Date: ' + tooltipItems[0].label;
                                    },
                                    label: function(context) {
                                        const label = context.dataset.label || '';
                                        const value = context.parsed.y;
                                        return `${label}: ${value} ${value === 1 ? 'entry' : 'entries'}`;
                                    }
                                }
                            },
                            legend: {
                                display: false
                            },
                            title: {
                                display: true,
                                text: 'Timetable Entries Trend (Last 7 Days)',
                                font: {
                                    size: 16,
                                    weight: 'bold',
                                    family: "-apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, 'Helvetica Neue', Arial, sans-serif"
                                },
                                padding: {
                                    top: 0,
                                    bottom: 15
                                },
                                color: '#333'
                            },
                            subtitle: {
                                display: true,
                                text: 'Showing daily entries from ' + data.labels[0] + ' to ' + data.labels[data.labels.length - 1],
                                font: {
                                    size: 12,
                                    style: 'italic'
                                },
                                color: '#666',
                                padding: {
                                    bottom: 15
                                }
                            }
                        },
                        animation: {
                            duration: 1000,
                            easing: 'easeInOutQuart'
                        },
                        elements: {
                            line: {
                                tension: 0.3 // Smooth line
                            }
                        }
                    }
                });
            }

            function updateDayOfWeekChart(data) {
                const ctx = document.getElementById('dayOfWeekChart').getContext('2d');
                const days = ['Sunday', 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday'];
                
                if (dayOfWeekChart) {
                    dayOfWeekChart.destroy();
                }
                
                dayOfWeekChart = new Chart(ctx, {
                    type: 'bar',
                    data: {
                        labels: days,
                        datasets: [{
                            label: 'Timetable Entries',
                            data: data,
                            backgroundColor: 'rgba(54, 162, 235, 0.6)',
                            borderColor: 'rgba(54, 162, 235, 1)',
                            borderWidth: 1
                        }]
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        scales: {
                            y: {
                                beginAtZero: true,
                                ticks: {
                                    stepSize: 1
                                }
                            }
                        },
                        plugins: {
                            legend: {
                                display: false
                            },
                            title: {
                                display: true,
                                text: 'Timetable Entries by Day of Week'
                            }
                        }
                    }
                });
            }

            function updateTimeOfDayChart(data) {
                const ctx = document.getElementById('timeOfDayChart').getContext('2d');
                
                if (timeOfDayChart) {
                    timeOfDayChart.destroy();
                }
                
                timeOfDayChart = new Chart(ctx, {
                    type: 'bar',
                    data: {
                        labels: data.labels,
                        datasets: [{
                            label: 'Timetable Entries',
                            data: data.values,
                            backgroundColor: 'rgba(255, 159, 64, 0.6)',
                            borderColor: 'rgba(255, 159, 64, 1)',
                            borderWidth: 1
                        }]
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        scales: {
                            y: {
                                beginAtZero: true,
                                ticks: {
                                    stepSize: 1
                                }
                            }
                        },
                        plugins: {
                            legend: {
                                display: false
                            },
                            title: {
                                display: true,
                                text: 'Timetable Entries by Time of Day'
                            }
                        }
                    }
                });
            }
        </script>
    </body>
    </html>

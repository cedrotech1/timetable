<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8" />
<meta name="viewport" content="width=device-width, initial-scale=1" />
<title>Select Lecturers - Frontend Pagination & Search</title>
<!-- Bootstrap CSS -->
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet" />
<style>
  table {
    font-size: 0.9rem;
  }
  .lecturer-card-container {
    display: flex;
    flex-wrap: wrap;
    gap: 0.5rem;
    margin-top: 0.75rem;
  }
  .lecturer-card {
    min-width: 180px;
    max-width: 220px;
    padding: 0.5rem 0.75rem;
    font-size: 0.9rem;
    position: relative;
    box-shadow: 0 .125rem .25rem rgba(0,0,0,.075);
    border-radius: 0.25rem;
    background-color: #fff;
  }
  .lecturer-card h5 {
    margin: 0;
    font-size: 1rem;
    font-weight: 600;
  }
  .lecturer-card p {
    margin: 0;
    font-size: 0.85rem;
    color: #555;
  }
  .lecturer-remove-btn {
    position: absolute;
    top: 6px;
    right: 8px;
    background: #dc3545;
    border: none;
    border-radius: 50%;
    color: white;
    width: 24px;
    height: 24px;
    font-weight: bold;
    line-height: 1;
    cursor: pointer;
    padding: 0;
  }
  .lecturer-action-btn {
    margin-right: 5px;
    font-size: 0.85rem;
    padding: 4px 8px;
    user-select: none;
    min-width: 110px;
  }
  .lecturer-selected-btn {
    opacity: 0.65;
    pointer-events: none;
  }
  #searchInput {
    max-width: 350px;
    margin-bottom: 1rem;
  }
  #pagination {
    margin-top: 1rem;
  }
  #pagination button {
    margin: 0 2px;
  }
</style>
</head>
<body>

<div class="container mt-4">
  <h2>Select Module Leader & Lecturers</h2>

  <div class="input-group mb-3">
    <input
      type="search"
      id="searchInput"
      class="form-control"
      placeholder="Search lecturers by name or email..."
      aria-label="Search lecturers"
    />
    <button class="btn btn-outline-secondary" type="button" id="searchButton">
      <i class="bi bi-search"></i> Search
    </button>
    <button class="btn btn-outline-secondary" type="button" id="clearSearch">
      <i class="bi bi-x-lg"></i>
    </button>
  </div>

  <table class="table table-striped table-bordered align-middle mt-2">
    <thead class="table-light">
      <tr>
        <th>Name</th>
        <th>Email</th>
        <th>Phone</th>
        <th style="min-width: 240px;">Actions</th>
      </tr>
    </thead>
    <tbody id="lecturerTable"></tbody>
  </table>

  <div class="d-flex justify-content-between align-items-center mt-3">
    <div id="pageInfo" class="text-muted small"></div>
    <div id="pagination" class="d-flex justify-content-center"></div>
    <div class="small text-muted">
      <select id="rowsPerPage" class="form-select form-select-sm d-inline-block w-auto">
        <option value="5">5 per page</option>
        <option value="10" selected>10 per page</option>
        <option value="20">20 per page</option>
        <option value="50">50 per page</option>
        <option value="100">100 per page</option>
        <option value="200">200 per page</option>
      </select>
    </div>
  </div>

  <h4 class="mt-4">Module Leader</h4>
  <div id="leaderCard" class="lecturer-card-container"></div>

  <h4 class="mt-4">Other Lecturers</h4>
  <div id="otherCards" class="lecturer-card-container"></div>
</div>

<!-- Bootstrap Bundle JS -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>

<script>
  const apiUrl = './get_lecturers.php';
  let allLecturers = []; // will hold all fetched lecturers
  let filteredLecturers = []; // lecturers filtered by search
  let rowsPerPage = 10; // default number of rows per page
  let currentPage = 1;
  let searchTerm = '';
  let selected = JSON.parse(localStorage.getItem('selectedLecturers')) || { leader: null, others: [] };

  // Fetch all lecturers
  async function fetchAllLecturers() {
    try {
      console.log('Fetching lecturers...');
      const res = await fetch(apiUrl);
      const json = await res.json();

      if (json.success) {
        allLecturers = json.data || [];
        console.log('Fetched lecturers:', allLecturers);
        
        // Always update filteredLecturers with all lecturers
        // Search will be handled by the search function
        filteredLecturers = [...allLecturers];
        
        currentPage = 1;
        renderTable();
        renderPagination();
        renderCards();
      } else {
        console.error('Failed to load lecturers:', json.message || 'Unknown error');
        alert('Failed to load lecturers: ' + (json.message || 'Unknown error'));
      }
    } catch (e) {
      alert('Error loading lecturers: ' + e.message);
    }
  }

  // Filter lecturers based on search term
  function filterLecturers() {
    console.log('--- filterLecturers called ---');
    console.log('Current search term:', searchTerm || '[empty]');
    
    if (!allLecturers || allLecturers.length === 0) {
      console.log('No lecturers data available');
      filteredLecturers = [];
      return;
    }
    
    if (!searchTerm) {
      console.log('No search term, showing all', allLecturers.length, 'lecturers');
      filteredLecturers = [...allLecturers];
      return;
    }
    
    const term = searchTerm.toLowerCase().trim();
    console.log(`Filtering ${allLecturers.length} lecturers with term:`, term);
    
    filteredLecturers = allLecturers.filter(lecturer => {
      const nameMatch = lecturer.names && lecturer.names.toLowerCase().includes(term);
      const emailMatch = lecturer.email && lecturer.email.toLowerCase().includes(term);
      const phoneMatch = lecturer.phone && lecturer.phone.toString().includes(term);
      
      if (nameMatch || emailMatch || phoneMatch) {
        console.log('Match found:', {
          name: lecturer.names,
          email: lecturer.email,
          phone: lecturer.phone,
          matches: { nameMatch, emailMatch, phoneMatch }
        });
        return true;
      }
      return false;
    });
    
    console.log(`Found ${filteredLecturers.length} matching lecturers`);
  }

  // Render table with current page of filteredLecturers
  function renderTable() {
    console.log('--- renderTable called ---');
    const tbody = document.getElementById('lecturerTable');
    tbody.innerHTML = '';

    if (!filteredLecturers || filteredLecturers.length === 0) {
      console.log('No filtered lecturers to display');
      tbody.innerHTML = `<tr><td colspan="4" class="text-center">No lecturers found.</td></tr>`;
      renderPagination();
      return;
    }

    console.log(`Displaying ${filteredLecturers.length} filtered lecturers`);
    
    const start = (currentPage - 1) * rowsPerPage;
    const end = Math.min(start + rowsPerPage, filteredLecturers.length);
    const pageLecturers = filteredLecturers.slice(start, end);
    
    console.log(`Showing ${pageLecturers.length} lecturers (${start + 1} to ${end} of ${filteredLecturers.length})`);

    pageLecturers.forEach(l => {
      const isLeader = selected.leader && selected.leader.id === l.id;
      const isOther = selected.others.some(o => o.id === l.id);
      const tr = document.createElement('tr');

      tr.innerHTML = `
        <td>${l.names}</td>
        <td>${l.email}</td>
        <td>${l.phone}</td>
        <td>
          <button type="button" class="btn btn-primary btn-sm lecturer-action-btn leader-btn ${isLeader ? 'lecturer-selected-btn' : ''}" ${isLeader ? 'disabled' : ''} title="Select as Module Leader">Module Leader</button>
          <button type="button" class="btn btn-success btn-sm lecturer-action-btn lecturer-btn ${isLeader || isOther ? 'lecturer-selected-btn' : ''}" ${isLeader || isOther ? 'disabled' : ''} title="Add as Lecturer">Add Lecturer</button>
        </td>
      `;

      tr.querySelector('.leader-btn').addEventListener('click', () => {
        selected.leader = l;
        selected.others = selected.others.filter(o => o.id !== l.id);
        saveSelection();
        renderCards();
        renderTable();
      });

      tr.querySelector('.lecturer-btn').addEventListener('click', () => {
        if (!(selected.leader && selected.leader.id === l.id) && !selected.others.some(o => o.id === l.id)) {
          selected.others.push(l);
          saveSelection();
          renderCards();
          renderTable();
        }
      });

      tbody.appendChild(tr);
    });

    renderPagination();
  }

  // Render pagination buttons for filtered lecturers
  function renderPagination() {
    const paginationDiv = document.getElementById('pagination');
    const pageInfo = document.getElementById('pageInfo');
    paginationDiv.innerHTML = '';

    const totalItems = filteredLecturers.length;
    const totalPages = Math.ceil(totalItems / rowsPerPage);
    
    // Update page info
    const startItem = totalItems > 0 ? (currentPage - 1) * rowsPerPage + 1 : 0;
    const endItem = Math.min(currentPage * rowsPerPage, totalItems);
    pageInfo.textContent = `Showing ${startItem} to ${endItem} of ${totalItems} entries`;

    if (totalPages <= 1) return;

    // First page button
    const firstBtn = createPaginationButton('«', currentPage === 1, () => {
      currentPage = 1;
      renderTable();
    });
    paginationDiv.appendChild(firstBtn);

    // Previous button
    const prevBtn = createPaginationButton('‹', currentPage === 1, () => {
      if (currentPage > 1) {
        currentPage--;
        renderTable();
      }
    });
    paginationDiv.appendChild(prevBtn);

    // Page numbers with ellipsis
    let startPage = Math.max(1, currentPage - 2);
    let endPage = Math.min(totalPages, startPage + 4);
    
    if (endPage - startPage < 4) {
      startPage = Math.max(1, endPage - 4);
    }

    // Add first page if not in range
    if (startPage > 1) {
      const firstPageBtn = createPaginationButton('1', false, () => {
        currentPage = 1;
        renderTable();
      });
      paginationDiv.appendChild(firstPageBtn);
      
      if (startPage > 2) {
        const ellipsis = document.createElement('span');
        ellipsis.className = 'px-2';
        ellipsis.textContent = '...';
        paginationDiv.appendChild(ellipsis);
      }
    }

    // Page numbers
    for (let p = startPage; p <= endPage; p++) {
      const btn = createPaginationButton(p.toString(), p === currentPage, () => {
        currentPage = p;
        renderTable();
      });
      if (p === currentPage) {
        btn.classList.add('active');
      }
      paginationDiv.appendChild(btn);
    }

    // Add last page if not in range
    if (endPage < totalPages) {
      if (endPage < totalPages - 1) {
        const ellipsis = document.createElement('span');
        ellipsis.className = 'px-2';
        ellipsis.textContent = '...';
        paginationDiv.appendChild(ellipsis);
      }
      
      const lastPageBtn = createPaginationButton(totalPages.toString(), false, () => {
        currentPage = totalPages;
        renderTable();
      });
      paginationDiv.appendChild(lastPageBtn);
    }

    // Next button
    const nextBtn = createPaginationButton('›', currentPage >= totalPages, () => {
      if (currentPage < totalPages) {
        currentPage++;
        renderTable();
      }
    });
    paginationDiv.appendChild(nextBtn);

    // Last page button
    const lastBtn = createPaginationButton('»', currentPage >= totalPages, () => {
      currentPage = totalPages;
      renderTable();
    });
    paginationDiv.appendChild(lastBtn);
  }
  
  // Helper function to create pagination buttons
  function createPaginationButton(text, isDisabled, onClick) {
    const btn = document.createElement('button');
    btn.type = 'button';
    btn.className = 'btn btn-sm mx-1';
    btn.classList.add(isDisabled ? 'btn-outline-secondary' : 'btn-outline-primary');
    btn.disabled = isDisabled;
    btn.textContent = text;
    btn.addEventListener('click', onClick);
    return btn;
  }

  // Render selected leader and other lecturers cards
  function renderCards() {
    const leaderContainer = document.getElementById('leaderCard');
    leaderContainer.innerHTML = '';
    if (selected.leader) {
      const card = document.createElement('div');
      card.className = 'lecturer-card';
      card.innerHTML = `
        <h5>${selected.leader.names}</h5>
        <p>${selected.leader.email}</p>
        <button type="button" class="lecturer-remove-btn" aria-label="Remove leader">×</button>
      `;
      card.querySelector('.lecturer-remove-btn').addEventListener('click', () => {
        selected.leader = null;
        saveSelection();
        renderCards();
        renderTable();
      });
      leaderContainer.appendChild(card);
    }

    const otherContainer = document.getElementById('otherCards');
    otherContainer.innerHTML = '';
    selected.others.forEach(l => {
      const card = document.createElement('div');
      card.className = 'lecturer-card';
      card.innerHTML = `
        <h5>${l.names}</h5>
        <p>${l.email}</p>
        <button type="button" class="lecturer-remove-btn" aria-label="Remove lecturer">×</button>
      `;
      card.querySelector('.lecturer-remove-btn').addEventListener('click', () => {
        selected.others = selected.others.filter(o => o.id !== l.id);
        saveSelection();
        renderCards();
        renderTable();
      });
      otherContainer.appendChild(card);
    });
  }

  function saveSelection() {
    localStorage.setItem('selectedLecturers', JSON.stringify(selected));
  }

  function debounce(fn, delay) {
    let timeoutId;
    return (...args) => {
      clearTimeout(timeoutId);
      timeoutId = setTimeout(() => fn(...args), delay);
    };
  }

  // Debounced search function
  const performSearch = debounce(function() {
    console.log('--- performSearch triggered ---');
    const searchInput = document.getElementById('searchInput');
    if (!searchInput) {
      console.error('Search input element not found');
      return;
    }
    
    const newSearchTerm = searchInput.value.trim();
    console.log('New search term:', newSearchTerm);
    
    // Only proceed if search term has changed
    if (newSearchTerm === searchTerm) {
      console.log('Search term unchanged, skipping');
      return;
    }
    
    searchTerm = newSearchTerm;
    currentPage = 1;
    
    console.log('Performing search for:', searchTerm || '[empty]');
    console.log('Current allLecturers:', allLecturers);
    
    // If search term is empty, show all lecturers
    if (!searchTerm) {
      console.log('Empty search term, showing all lecturers');
      filteredLecturers = [...allLecturers];
      renderTable();
      renderPagination();
      return;
    }
    
    console.log('Applying search filter...');
    
    // Apply search filter
    filterLecturers();
    
    console.log('Filtered results:', filteredLecturers);
    
    renderTable();
    renderPagination();
  }, 300); // 300ms delay for debounce

  // Search button click handler
  document.getElementById('searchButton').addEventListener('click', performSearch);

  // Clear search button
  document.getElementById('clearSearch').addEventListener('click', () => {
    document.getElementById('searchInput').value = '';
    searchTerm = '';
    currentPage = 1;
    renderTable();
  });

  // Allow search on Enter key
  document.getElementById('searchInput').addEventListener('keypress', (e) => {
    if (e.key === 'Enter') {
      performSearch();
    }
  });

  // Rows per page change handler
  document.getElementById('rowsPerPage').addEventListener('change', (e) => {
    rowsPerPage = parseInt(e.target.value);
    currentPage = 1; // Reset to first page when changing rows per page
    renderTable();
  });

  // Initial fetch
  console.log('Initializing page...');
  
  // Initialize when DOM is fully loaded
  document.addEventListener('DOMContentLoaded', function() {
    const searchInput = document.getElementById('searchInput');
    const searchButton = document.getElementById('searchButton');
    const clearButton = document.getElementById('clearSearch');
    
    // Handle search input changes with debounce
    if (searchInput) {
      searchInput.addEventListener('input', function() {
        performSearch();
      });
    }
    
    // Search button click handler
    if (searchButton) {
      searchButton.addEventListener('click', function(e) {
        e.preventDefault();
        performSearch();
      });
    }
    
    // Clear search button
    if (clearButton) {
      clearButton.addEventListener('click', function(e) {
        e.preventDefault();
        if (searchInput) searchInput.value = '';
        searchTerm = '';
        currentPage = 1;
        filteredLecturers = [...allLecturers];
        renderTable();
        renderPagination();
      });
    }
    
    // Initial data load
    fetchAllLecturers();
  });
</script>


</body>
</html>

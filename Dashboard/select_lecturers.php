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

    <div class="mt-4">
      <h2>Select Module Leader & Lecturers</h2>

      <div id="noGroupsMessage" class="alert alert-warning d-none">
        <i class="bi bi-exclamation-triangle-fill me-2"></i>
        No groups selected. Please select groups first before choosing lecturers.
        <a href="timetable_set.php#lecturers" onclick="window.location.reload(); return false;" class="alert-link ms-2">
          <i class="bi bi-arrow-right-circle"></i> Get lecturers
        </a>
      </div>

      <div id="lecturerControls" class="d-none">
      <div class="input-group mb-3">
        <input
          type="search"
          id="lecturerSearchInput"
          class="form-control"
          placeholder="Search lecturers by name or email..."
          aria-label="Search lecturers"
        />
        <button class="btn btn-outline-secondary" type="button" id="lecturerSearchButton">
          <i class="bi bi-search"></i> Search
        </button>
        <button class="btn btn-outline-secondary" type="button" id="lecturerClearSearch">
          <i class="bi bi-x-lg"></i>
        </button>
      </div>

      <table class="table table-striped table-bordered align-middle mt-2">
        <thead class="table-light">
          <tr>
            <th>Name</th>
            <th>Personal Email</th>
            <th>UR Email</th>
            <th>Phone</th>
            <th style="min-width: 240px;">Actions</th>
          </tr>
        </thead>
        <tbody id="lecturerTable"></tbody>
      </table>

      <div class="d-flex justify-content-between align-items-center mt-3">
        <div id="lecturerPageInfo" class="text-muted small"></div>
        <div id="lecturerPagination" class="d-flex justify-content-center"></div>
        <div class="small text-muted">
          <select id="lecturerRowsPerPage" class="form-select form-select-sm d-inline-block w-auto">
            <option value="5">5 per page</option>
            <option value="10" selected>10 per page</option>
            <option value="20">20 per page</option>
            <option value="50">50 per page</option>
          </select>
        </div>
      </div>
    </div> <!-- Close lecturerControls -->

    <h4 class="mt-4">Module Leader</h4>
  <div id="leaderCard" class="lecturer-card-container"></div>

  <h4 class="mt-4">Other Lecturers</h4>
  <div id="otherCards" class="lecturer-card-container"></div>
</div>

<!-- Bootstrap Bundle JS -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>

<script>
  const apiUrl = './get_lecturers.php';
  let rowsPerPage = 10;
  let currentPage = 1;
  let searchTerm = '';
  let lecturers = [];
  let pagination = { total: 0, page: 1, pages: 1, limit: rowsPerPage };
  let selected = JSON.parse(localStorage.getItem('selectedLecturers')) || { leader: null, others: [] };
  
  // Check if groups are selected
  function checkSelectedGroups() {
    const selectedGroups = JSON.parse(localStorage.getItem('selectedGroups')) || [];
    const noGroupsMessage = document.getElementById('noGroupsMessage');
    const lecturerControls = document.getElementById('lecturerControls');
    
    if (selectedGroups.length === 0) {
      noGroupsMessage.classList.remove('d-none');
      lecturerControls.classList.add('d-none');
      return false;
    } else {
      noGroupsMessage.classList.add('d-none');
      lecturerControls.classList.remove('d-none');
      return true;
    }
  }

  async function fetchLecturers() {
    // Check if groups are selected before proceeding
    if (!checkSelectedGroups()) {
      return;
    }
    
    try {
      const params = new URLSearchParams({
        page: currentPage.toString(),
        limit: rowsPerPage.toString()
      });
      if (searchTerm) {
        params.append('search', searchTerm);
      }

      const response = await fetch(`${apiUrl}?${params.toString()}`);
      const json = await response.json();

      if (!json.success) {
        throw new Error(json.message || 'Unknown error');
      }

      lecturers = json.data || [];
      pagination = json.pagination || pagination;
      currentPage = pagination.page || currentPage;
      if (pagination.limit) {
        const parsedLimit = parseInt(pagination.limit, 10);
        if (!Number.isNaN(parsedLimit)) {
          rowsPerPage = parsedLimit;
        }
      }

      renderTable();
      renderPagination();
      renderCards();
    } catch (error) {
      console.error('Error loading lecturers:', error);
      alert('Error loading lecturers: ' + error.message);
    }
  }

  function renderTable() {
    const tbody = document.getElementById('lecturerTable');
    tbody.innerHTML = '';

    if (!lecturers || lecturers.length === 0) {
      tbody.innerHTML = `<tr><td colspan="5" class="text-center">No lecturers found.</td></tr>`;
      renderPagination();
      return;
    }

    lecturers.forEach((l) => {
      const isLeader = selected.leader && selected.leader.id === l.id;
      const isOther = selected.others.some((o) => o.id === l.id);
      const tr = document.createElement('tr');

      tr.innerHTML = `
        <td>${l.names ?? ''}</td>
        <td>${l.email ?? ''}</td>
        <td>${l.ur_email ?? ''}</td>
        <td>${l.phone ?? ''}</td>
        <td>
          <button type="button" class="btn btn-primary btn-sm lecturer-action-btn leader-btn ${isLeader ? 'lecturer-selected-btn' : ''}" ${isLeader ? 'disabled' : ''} title="Select as Module Leader">Module Leader</button>
          <button type="button" class="btn btn-success btn-sm lecturer-action-btn lecturer-btn ${isLeader || isOther ? 'lecturer-selected-btn' : ''}" ${isLeader || isOther ? 'disabled' : ''} title="Add as Lecturer">Add Lecturer</button>
        </td>
      `;

      tr.querySelector('.leader-btn').addEventListener('click', () => {
        selected.leader = l;
        selected.others = selected.others.filter((o) => o.id !== l.id);
        saveSelection();
        renderCards();
        renderTable();
      });

      tr.querySelector('.lecturer-btn').addEventListener('click', () => {
        if (!(selected.leader && selected.leader.id === l.id) && !selected.others.some((o) => o.id === l.id)) {
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

  function renderPagination() {
    const paginationDiv = document.getElementById('lecturerPagination');
    const pageInfo = document.getElementById('lecturerPageInfo');
    paginationDiv.innerHTML = '';

    const totalItems = pagination.total || 0;
    const totalPages = pagination.pages || Math.max(1, Math.ceil(totalItems / rowsPerPage));

    const startItem = totalItems === 0 ? 0 : (currentPage - 1) * rowsPerPage + 1;
    const endItem = totalItems === 0 ? 0 : Math.min(currentPage * rowsPerPage, totalItems);
    pageInfo.textContent = `Showing ${startItem} to ${endItem} of ${totalItems} entries`;

    if (totalPages <= 1) {
      return;
    }

    const firstBtn = createPaginationButton('«', currentPage === 1, () => {
      currentPage = 1;
      fetchLecturers();
    });
    paginationDiv.appendChild(firstBtn);

    const prevBtn = createPaginationButton('‹', currentPage === 1, () => {
      if (currentPage > 1) {
        currentPage -= 1;
        fetchLecturers();
      }
    });
    paginationDiv.appendChild(prevBtn);

    let startPage = Math.max(1, currentPage - 2);
    let endPage = Math.min(totalPages, startPage + 4);
    if (endPage - startPage < 4) {
      startPage = Math.max(1, endPage - 4);
    }

    if (startPage > 1) {
      paginationDiv.appendChild(createPaginationButton('1', false, () => {
        currentPage = 1;
        fetchLecturers();
      }));
      if (startPage > 2) {
        const ellipsis = document.createElement('span');
        ellipsis.className = 'px-2';
        ellipsis.textContent = '...';
        paginationDiv.appendChild(ellipsis);
      }
    }

    for (let p = startPage; p <= endPage; p++) {
      const btn = createPaginationButton(p.toString(), p === currentPage, () => {
        currentPage = p;
        fetchLecturers();
      });
      if (p === currentPage) {
        btn.classList.add('active');
      }
      paginationDiv.appendChild(btn);
    }

    if (endPage < totalPages) {
      if (endPage < totalPages - 1) {
        const ellipsis = document.createElement('span');
        ellipsis.className = 'px-2';
        ellipsis.textContent = '...';
        paginationDiv.appendChild(ellipsis);
      }
      paginationDiv.appendChild(createPaginationButton(totalPages.toString(), false, () => {
        currentPage = totalPages;
        fetchLecturers();
      }));
    }

    const nextBtn = createPaginationButton('›', currentPage >= totalPages, () => {
      if (currentPage < totalPages) {
        currentPage += 1;
        fetchLecturers();
      }
    });
    paginationDiv.appendChild(nextBtn);

    const lastBtn = createPaginationButton('»', currentPage >= totalPages, () => {
      if (currentPage < totalPages) {
        currentPage = totalPages;
        fetchLecturers();
      }
    });
    paginationDiv.appendChild(lastBtn);
  }
  
  function createPaginationButton(text, isDisabled, onClick) {
    const btn = document.createElement('button');
    btn.type = 'button';
    btn.className = 'btn btn-sm mx-1';
    btn.classList.add(isDisabled ? 'btn-outline-secondary' : 'btn-outline-primary');
    btn.disabled = isDisabled;
    btn.textContent = text;
    if (!isDisabled) {
      btn.addEventListener('click', onClick);
    }
    return btn;
  }

  function renderCards() {
    const leaderContainer = document.getElementById('leaderCard');
    leaderContainer.innerHTML = '';
    if (selected.leader) {
      const card = document.createElement('div');
      card.className = 'lecturer-card';
      card.innerHTML = `
        <h5>${selected.leader.names}</h5>
        <p class="mb-1"><strong>Personal:</strong> ${selected.leader.email ?? ''}</p>
        <p class="mb-0"><strong>UR:</strong> ${selected.leader.ur_email ?? ''}</p>
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
    selected.others.forEach((l) => {
      const card = document.createElement('div');
      card.className = 'lecturer-card';
      card.innerHTML = `
        <h5>${l.names}</h5>
        <p class="mb-1"><strong>Personal:</strong> ${l.email ?? ''}</p>
        <p class="mb-0"><strong>UR:</strong> ${l.ur_email ?? ''}</p>
        <button type="button" class="lecturer-remove-btn" aria-label="Remove lecturer">×</button>
      `;
      card.querySelector('.lecturer-remove-btn').addEventListener('click', () => {
        selected.others = selected.others.filter((o) => o.id !== l.id);
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

  function handleSearch() {
    const input = document.getElementById('lecturerSearchInput');
    searchTerm = input ? input.value.trim() : '';
    currentPage = 1;
    fetchLecturers();
  }

  const debouncedHandleSearch = debounce(handleSearch, 300);

  document.addEventListener('DOMContentLoaded', () => {
    // Initialize the page and check for selected groups
    checkSelectedGroups();
    
    // Only fetch lecturers if groups are selected
    if (checkSelectedGroups()) {
      fetchLecturers();
    }
    
    const searchInput = document.getElementById('lecturerSearchInput');
    const searchButton = document.getElementById('lecturerSearchButton');
    const clearButton = document.getElementById('lecturerClearSearch');
    const rowsSelector = document.getElementById('lecturerRowsPerPage');

    if (searchInput) {
      searchInput.addEventListener('input', debouncedHandleSearch);
      searchInput.addEventListener('keypress', (e) => {
        if (e.key === 'Enter') {
          e.preventDefault();
          handleSearch();
        }
      });
    }

    if (searchButton) {
      searchButton.addEventListener('click', (e) => {
        e.preventDefault();
        handleSearch();
      });
    }

    if (clearButton) {
      clearButton.addEventListener('click', (e) => {
        e.preventDefault();
        if (searchInput) {
          searchInput.value = '';
        }
        searchTerm = '';
        currentPage = 1;
        fetchLecturers();
      });
    }

    if (rowsSelector) {
      rowsSelector.addEventListener('change', (e) => {
        const value = parseInt(e.target.value, 10);
        rowsPerPage = Number.isNaN(value) ? 10 : value;
        currentPage = 1;
        fetchLecturers();
      });
    }
  }); // Close DOMContentLoaded event listener
</script>

</body>
</html>

<?php
// module_selector.php (vanilla JS, no jQuery)
// session_start(); // if you need session
?>
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8"/>
<meta name="viewport" content="width=device-width,initial-scale=1"/>
<title>Module Selector (vanilla JS)</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet"/>
<style>
  body { background:#f8f9fa; padding:18px; }
  .small-card { font-size:0.92rem; padding:8px; }
  .compact-card { margin-bottom:6px; position:relative; padding-top:1.4rem; }
  .compact-card .remove-btn { position:absolute; top:6px; right:6px; width:28px; height:28px; padding:0; }
  table th.sortable { cursor:pointer; user-select:none; }
  .controls-row .form-control, .controls-row .form-select { min-height:38px; }
  #modulesTable tbody tr td { vertical-align: middle; }
  #selectedModuleCard .card-body { padding:0.6rem 0.9rem; }
  .btn-sm { padding:.25rem .4rem; font-size:.78rem; }
</style>
</head>
<body>

<div class="container" style="max-width:980px">
  <h2 class="">Select Module</h3>

  <!-- Selected module card -->
  <div id="selectedModuleCard" class="card mb-3 d-none small-card compact-card">
    <div class="card-body d-flex justify-content-between align-items-start">
      <div>
        <strong id="sel_name"></strong>
        <div class="text-muted small">Code: <span id="sel_code"></span> — Credits: <span id="sel_credits"></span></div>
        <div class="text-muted small">Year: <span id="sel_year"></span>, Sem: <span id="sel_semester"></span></div>
        <div class="text-muted small">Program ID: <span id="sel_program"></span></div>
      </div>
      <div class="text-end">
        <button id="changeModuleBtn" class="btn btn-sm btn-outline-primary">Change</button>
      </div>
    </div>
  </div>

  <!-- Controls + table wrapper -->
  <div id="moduleControls">
    <div class="row g-2 controls-row mb-2">
      <div class="col-md-6">
        <input id="searchInput" class="form-control" placeholder="Search name / code / year / semester..." />
      </div>
      <div class="col-md-2">
        <select id="yearFilter" class="form-select">
          <option value="">All years</option>
          <option>1</option><option>2</option><option>3</option><option>4</option>
        </select>
      </div>
      <div class="col-md-2">
        <select id="semesterFilter" class="form-select">
          <option value="">All semesters</option>
          <option>1</option><option>2</option>
        </select>
      </div>
      <div class="col-md-2 text-end">
        <a href="timetable.php"><button id="" class="btn btn-outline-secondary btn-sm w-100">
          <i class="bi bi-arrow-clockwise"></i> Refresh
        </button></a>
      </div>
    </div>

    <div class="table-responsive mb-2">
      <table class="table table-sm table-striped table-bordered" id="modulesTable">
        <thead class="table-light">
          <tr>
            <th class="sortable" data-field="name">Name <span class="sort-indicator"></span></th>
            <th class="sortable" data-field="credits">Credits <span class="sort-indicator"></span></th>
            <th class="sortable" data-field="code">Code <span class="sort-indicator"></span></th>
            <th class="sortable" data-field="year">Year <span class="sort-indicator"></span></th>
            <th class="sortable" data-field="semester">Semester <span class="sort-indicator"></span></th>
            <th>Program</th>
            <th style="width:100px">Action</th>
          </tr>
        </thead>
        <tbody id="modulesTbody"></tbody>
      </table>
    </div>

    <nav><ul id="pagination" class="pagination pagination-sm justify-content-center"></ul></nav>
  </div>
</div>

<script>
// Make the main function async to use await
(async () => {
  // config & state
  const perPage = 10;
  let page = 1, pages = 1;
  let search = '', program_id = 0, year = '', semester = '';
  let sortField = 'name', sortOrder = 'asc'; // asc|desc
  let debounceTimer = null;
  let selectedProgramIds = [];
  
  // Get selected groups from localStorage and fetch actual program IDs
  try {
    const selectedGroups = JSON.parse(localStorage.getItem('selectedGroups') || '[]');
    console.log('Selected groups from localStorage:', selectedGroups);
    
    // Create a Set to store unique program names
    const programNames = new Set();
    
    // First, collect all unique program names from selected groups
    selectedGroups.forEach(group => {
      if (group && group.programName) {
        programNames.add(group.programName);
      }
    });
    
    console.log('Unique program names to fetch:', Array.from(programNames));
    
    // If we have program names, fetch their IDs from the server
    if (programNames.size > 0) {
      try {
        // Convert Set to array and encode for URL
        const programNamesArray = Array.from(programNames);
        const response = await fetch(`get_programs_by_name.php?names=${encodeURIComponent(JSON.stringify(programNamesArray))}`);
        const programs = await response.json();
        
        if (programs && programs.success && programs.data) {
          // Map program names to their IDs
          const programNameToId = {};
          programs.data.forEach(program => {
            programNameToId[program.name] = program.id;
          });
          
          // Now map each selected group to its program ID
          selectedGroups.forEach(group => {
            if (group && group.programName && programNameToId[group.programName]) {
              const programId = programNameToId[group.programName];
              if (!selectedProgramIds.includes(programId)) {
                selectedProgramIds.push(programId);
                console.log(`Group: ${group.name}, Program: ${group.programName}, Program ID: ${programId}`);
              }
            }
          });
          
          console.log('Mapped program IDs:', selectedProgramIds);
          
          // If we have program IDs, set the first one as the default
          if (selectedProgramIds.length > 0) {
            program_id = selectedProgramIds[0];
            console.log('Setting program_id to:', program_id);
          }
        } else {
          console.error('Failed to fetch program IDs:', programs?.message || 'Unknown error');
        }
      } catch (error) {
        console.error('Error fetching program IDs:', error);
      }
    }
  } catch (e) {
    console.error('Error parsing selected groups from localStorage:', e);
  }

  // Initialize the application after fetching program IDs
  function initApp() {
    console.log('Initializing app with program IDs:', selectedProgramIds);
    // DOM refs
    const el = id => document.getElementById(id);
    const modulesTbody = el('modulesTbody');
    const paginationEl = el('pagination');
    const searchInput = el('searchInput');
    const programFilter = el('programFilter');
    const yearFilter = el('yearFilter');
    const semesterFilter = el('semesterFilter');
    const refreshBtn = el('refreshBtn');
    const selectedCard = el('selectedModuleCard');
    const changeBtn = el('changeModuleBtn');
    const moduleControls = el('moduleControls');
    
    // If we're not on the module selector page, don't initialize the rest
    if (!modulesTbody) {
      console.log('Module selector elements not found, skipping initialization');
      return;
    }

    // module map: id -> module object (so selection via delegation is easy)
    const moduleMap = new Map();

    // Load programs for display (kept for backward compatibility)
    function loadPrograms() {
      return Promise.resolve(); // No longer needed as we get program names with modules
    }

    // update sort indicators in headers
    function updateSortIndicators(){
      document.querySelectorAll('th.sortable').forEach(th => {
        const f = th.dataset.field;
        const sp = th.querySelector('.sort-indicator');
        if(!sp) return;
        sp.textContent = (f === sortField) ? (sortOrder === 'asc' ? ' ▲' : ' ▼') : '';
      });
    }

    // load modules via fetch
    function loadModules() {
      // Always use selectedProgramIds for filtering if available
      const programFilter = selectedProgramIds.length > 0 ? selectedProgramIds.join(',') : program_id;
      
      // Log the program IDs being used for filtering
      console.log('Filtering modules by program IDs:', programFilter);
      
      const params = new URLSearchParams({
        search, 
        program_id: programFilter,
        year, 
        semester, 
        sort: sortField, 
        order: sortOrder,
        page, 
        perPage
      });
      
      // show loading row
      modulesTbody.innerHTML = '<tr><td colspan="7" class="text-center text-muted">Loading…</td></tr>';

      fetch(`api_get_modules.php?${params.toString()}`, {cache:'no-cache'})
        .then(async response => {
          const text = await response.text();
          try {
            return JSON.parse(text);
          } catch (e) {
            console.error('Failed to parse JSON:', text);
            throw new Error('Invalid JSON response from server');
          }
        })
        .then(json => {
          if(!json || !json.success){
            const errorMsg = json && json.message ? json.message : 'Failed to load modules';
            modulesTbody.innerHTML = `<tr><td colspan="7" class="text-center text-danger">${errorMsg}</td></tr>`;
            console.error('API Error:', json);
            return;
          }
          const data = json.data || [];
          const total = json.total || 0;
          pages = json.pages || 1;

          moduleMap.clear();
          modulesTbody.innerHTML = '';

          if(data.length === 0){
            modulesTbody.innerHTML = '<tr><td colspan="7" class="text-center">No modules found.</td></tr>';
          } else {
            // build rows
            for(const m of data){
              moduleMap.set(String(m.id), m);
              const tr = document.createElement('tr');

              const tdName = document.createElement('td'); tdName.textContent = m.name; tr.appendChild(tdName);
              const tdCredits = document.createElement('td'); tdCredits.textContent = m.credits; tr.appendChild(tdCredits);
              const tdCode = document.createElement('td'); tdCode.textContent = m.code; tr.appendChild(tdCode);
              const tdYear = document.createElement('td'); tdYear.textContent = m.year; tr.appendChild(tdYear);
              const tdSem = document.createElement('td'); tdSem.textContent = m.semester; tr.appendChild(tdSem);
              const tdProg = document.createElement('td'); 
              tdProg.textContent = m.program_name || `Program ${m.program_id}`; 
              tr.appendChild(tdProg);

              const tdAct = document.createElement('td');
              const btn = document.createElement('button');
              btn.className = 'btn btn-sm btn-primary select-btn';
              btn.type = 'button';
              btn.dataset.id = m.id;
              btn.textContent = 'Select';
              tdAct.appendChild(btn);
              tr.appendChild(tdAct);

              modulesTbody.appendChild(tr);
            }
          }

          renderPagination();
          updateSortIndicators();
        })
        .catch(err => {
          console.error(err);
          modulesTbody.innerHTML = '<tr><td colspan="7" class="text-center text-danger">Error loading modules</td></tr>';
        });
    }

    // pagination rendering (compact)
    function renderPagination(){
      paginationEl.innerHTML = '';
      const createItem = (p, label, disabled=false, active=false) => {
        const li = document.createElement('li');
        li.className = 'page-item' + (disabled ? ' disabled' : '') + (active ? ' active' : '');
        const a = document.createElement('a');
        a.className = 'page-link';
        a.href = '#';
        a.dataset.page = p;
        a.textContent = label;
        a.addEventListener('click', (e) => {
          e.preventDefault();
          const np = Number(a.dataset.page);
          if(!disabled && np >= 1 && np <= pages && np !== page){ page = np; loadModules(); }
        });
        li.appendChild(a);
        return li;
      };

      paginationEl.appendChild(createItem(Math.max(1,page-1), '‹', page<=1));
      let start = Math.max(1, page-2);
      let end = Math.min(pages, start+4);
      if(end - start < 4) start = Math.max(1, end-4);
      for(let p = start; p <= end; p++){
        paginationEl.appendChild(createItem(p, p, false, p === page));
      }
      paginationEl.appendChild(createItem(Math.min(pages,page+1), '›', page>=pages));
    }

    // render selected module card; hide controls if selected
    function renderSelectedModule(){
      const s = localStorage.getItem('selectedModule');
      if(!s){
        selectedCard.classList.add('d-none');
        moduleControls.style.display = ''; // show controls & table
        return;
      }
      try {
        const m = JSON.parse(s);
        el('sel_name').textContent = m.name || '';
        el('sel_code').textContent = m.code || '';
        el('sel_credits').textContent = m.credits || '';
        el('sel_year').textContent = m.year || '';
        el('sel_semester').textContent = m.semester || '';
        el('sel_program').textContent = m.program_id || '';
        selectedCard.classList.remove('d-none');
        moduleControls.style.display = 'none'; // hide table + controls
      } catch(e) {
        console.error('Invalid selectedModule data', e);
        localStorage.removeItem('selectedModule');
        selectedCard.classList.add('d-none');
        moduleControls.style.display = '';
      }
    }

    // event bindings
    // delegated click for Select buttons
    modulesTbody.addEventListener('click', (ev) => {
      const btn = ev.target.closest('.select-btn');
      if(!btn) return;
      const id = String(btn.dataset.id);
      const mod = moduleMap.get(id);
      if(!mod) return;
      localStorage.setItem('selectedModule', JSON.stringify(mod));
      renderSelectedModule();
      // Do NOT show table again - per request
    });

    // change button
    if (changeBtn) {
      changeBtn.addEventListener('click', () => {
        localStorage.removeItem('selectedModule');
        renderSelectedModule();
        page = 1;
        loadModules();
      });
    }

    // search debounce
    if (searchInput) {
      searchInput.addEventListener('input', () => {
        clearTimeout(debounceTimer);
        debounceTimer = setTimeout(() => {
          search = searchInput.value.trim();
          page = 1;
          loadModules();
        }, 300);
      });
    }

    // filters - only add event listeners if elements exist
    if (yearFilter) {
      yearFilter.addEventListener('change', () => { 
        year = yearFilter.value || ''; 
        page = 1; 
        loadModules(); 
      });
    }
    
    if (semesterFilter) {
      semesterFilter.addEventListener('change', () => { 
        semester = semesterFilter.value || ''; 
        page = 1; 
        loadModules(); 
      });
    }

    // sorting (click header)
    const sortableHeaders = document.querySelectorAll('th.sortable');
    if (sortableHeaders.length > 0) {
      sortableHeaders.forEach(th => {
        th.addEventListener('click', () => {
          const f = th.dataset.field;
          if(sortField === f) sortOrder = (sortOrder === 'asc' ? 'desc' : 'asc');
          else { sortField = f; sortOrder = 'asc'; }
          page = 1;
          loadModules();
        });
      });
    }

    // init
    loadPrograms().then(() => {
      renderSelectedModule();
      if(!localStorage.getItem('selectedModule')) loadModules();
    });
    
    // Fix refresh button
    if (refreshBtn) {
      refreshBtn.addEventListener('click', (e) => {
        e.preventDefault();
        loadPrograms().then(() => loadModules());
      });
    }

    // Handle filter changes - only for elements that exist
    const filters = [programFilter, yearFilter, semesterFilter].filter(Boolean);
    filters.forEach(filter => {
      filter.addEventListener('change', () => {
        page = 1;
        loadModules();
      });
    });

    // Handle popstate (back/forward navigation)
    window.addEventListener('popstate', function() {
      page = 1;
      loadModules();
    });

    // Handle module selection
    document.addEventListener('click', function(e) {
      const btn = e.target.closest('.select-btn');
      if(!btn) return;
      const id = String(btn.dataset.id);
      const mod = moduleMap.get(id);
      if(!mod) return;
      localStorage.setItem('selectedModule', JSON.stringify(mod));
      renderSelectedModule();
      // Do NOT show table again - per request
    });

    // Handle search input with debounce
    searchInput.addEventListener('input', function() {
      clearTimeout(debounceTimer);
      debounceTimer = setTimeout(() => {
        search = searchInput.value.trim();
        page = 1;
        loadModules();
      }, 300);
    });
  }

  // Initialize the application
  function initApp() {
    console.log('Initializing app with program IDs:', selectedProgramIds);
    // DOM refs
    const el = id => document.getElementById(id);
    const modulesTbody = el('modulesTbody');
    const paginationEl = el('pagination');
    const searchInput = el('searchInput');
    const programFilter = el('programFilter');
    const yearFilter = el('yearFilter');
    const semesterFilter = el('semesterFilter');
    const refreshBtn = el('refreshBtn');
    const selectedCard = el('selectedModuleCard');
    const changeBtn = el('changeModuleBtn');
    const moduleControls = el('moduleControls');
    
    // If we're not on the module selector page, don't initialize the rest
    if (!modulesTbody) {
      console.log('Module selector elements not found, skipping initialization');
      return;
    }

    // module map: id -> module object (so selection via delegation is easy)
    const moduleMap = new Map();

    // Load programs for display (kept for backward compatibility)
    function loadPrograms() {
      return Promise.resolve(); // No longer needed as we get program names with modules
    }

    // load modules via fetch
    function loadModules() {
      // Always use selectedProgramIds for filtering if available
      const programFilter = selectedProgramIds.length > 0 ? selectedProgramIds.join(',') : program_id;
      
      // Log the program IDs being used for filtering
      console.log('Filtering modules by program IDs:', programFilter);
      
      const params = new URLSearchParams({
        search, 
        program_id: programFilter,
        year, 
        semester, 
        sort: sortField, 
        order: sortOrder,
        page, 
        perPage
      });
      
      // show loading row
      modulesTbody.innerHTML = '<tr><td colspan="7" class="text-center text-muted">Loading…</td></tr>';

      fetch(`api_get_modules.php?${params.toString()}`, {cache:'no-cache'})
        .then(async response => {
          const text = await response.text();
          try {
            return JSON.parse(text);
          } catch (e) {
            console.error('Failed to parse JSON:', text);
            throw new Error('Invalid JSON response from server');
          }
        })
        .then(json => {
          if(!json || !json.success){
            const errorMsg = json && json.message ? json.message : 'Failed to load modules';
            modulesTbody.innerHTML = `<tr><td colspan="7" class="text-center text-danger">${errorMsg}</td></tr>`;
            console.error('API Error:', json);
            return;
          }
          const data = json.data || [];
          const total = json.total || 0;
          pages = json.pages || 1;

          moduleMap.clear();
          modulesTbody.innerHTML = '';

          if(data.length === 0){
            modulesTbody.innerHTML = '<tr><td colspan="7" class="text-center">No modules found.</td></tr>';
          } else {
            // build rows
            for(const m of data){
              moduleMap.set(String(m.id), m);
              const tr = document.createElement('tr');

              const tdName = document.createElement('td'); tdName.textContent = m.name; tr.appendChild(tdName);
              const tdCredits = document.createElement('td'); tdCredits.textContent = m.credits; tr.appendChild(tdCredits);
              const tdCode = document.createElement('td'); tdCode.textContent = m.code; tr.appendChild(tdCode);
              const tdYear = document.createElement('td'); tdYear.textContent = m.year; tr.appendChild(tdYear);
              const tdSem = document.createElement('td'); tdSem.textContent = m.semester; tr.appendChild(tdSem);
              const tdProg = document.createElement('td'); 
              tdProg.textContent = m.program_name || `Program ${m.program_id}`; 
              tr.appendChild(tdProg);

              const tdAct = document.createElement('td');
              const btn = document.createElement('button');
              btn.className = 'btn btn-sm btn-primary select-btn';
              btn.type = 'button';
              btn.dataset.id = m.id;
              btn.textContent = 'Select';
              tdAct.appendChild(btn);
              tr.appendChild(tdAct);

              modulesTbody.appendChild(tr);
            }
          }

          renderPagination();
          updateSortIndicators();
        })
        .catch(err => {
          console.error(err);
          modulesTbody.innerHTML = '<tr><td colspan="7" class="text-center text-danger">Error loading modules</td></tr>';
        });
    }
  
    // update sort indicators in headers
    function updateSortIndicators(){
      document.querySelectorAll('th.sortable').forEach(th => {
        const f = th.dataset.field;
        const sp = th.querySelector('.sort-indicator');
        if(!sp) return;
        sp.textContent = (f === sortField) ? (sortOrder === 'asc' ? ' ▲' : ' ▼') : '';
      });
    }

    // render pagination controls
    function renderPagination() {
      if (!paginationEl) return;
      
      paginationEl.innerHTML = '';
      
      const createPageItem = (pageNum, label, active = false, disabled = false) => {
        const li = document.createElement('li');
        li.className = `page-item ${active ? 'active' : ''} ${disabled ? 'disabled' : ''}`;
        
        const a = document.createElement('a');
        a.className = 'page-link';
        a.href = '#';
        a.textContent = label;
        
        if (!disabled) {
          a.addEventListener('click', (e) => {
            e.preventDefault();
            page = pageNum;
            loadModules();
          });
        }
        
        li.appendChild(a);
        return li;
      };
      
      // Previous button
      paginationEl.appendChild(createPageItem(page - 1, 'Previous', false, page === 1));
      
      // Page numbers
      const maxPages = 5;
      let startPage = Math.max(1, page - Math.floor(maxPages / 2));
      let endPage = startPage + maxPages - 1;
      
      if (endPage > pages) {
        endPage = pages;
        startPage = Math.max(1, endPage - maxPages + 1);
      }
      
      if (startPage > 1) {
        paginationEl.appendChild(createPageItem(1, '1'));
        if (startPage > 2) {
          const li = document.createElement('li');
          li.className = 'page-item disabled';
          li.innerHTML = '<span class="page-link">...</span>';
          paginationEl.appendChild(li);
        }
      }
      
      for (let i = startPage; i <= endPage; i++) {
        paginationEl.appendChild(createPageItem(i, i.toString(), i === page));
      }
      
      if (endPage < pages) {
        if (endPage < pages - 1) {
          const li = document.createElement('li');
          li.className = 'page-item disabled';
          li.innerHTML = '<span class="page-link">...</span>';
          paginationEl.appendChild(li);
        }
        paginationEl.appendChild(createPageItem(pages, pages.toString()));
      }
      
      // Next button
      paginationEl.appendChild(createPageItem(page + 1, 'Next', false, page >= pages));
    }
    
    // render selected module card
    function renderSelectedModule() {
      if (!selectedCard || !moduleControls) return;
      
      const selectedModule = localStorage.getItem('selectedModule');
      if (!selectedModule) {
        selectedCard.classList.add('d-none');
        moduleControls.style.display = '';
        return;
      }
      
      try {
        const module = JSON.parse(selectedModule);
        selectedCard.classList.remove('d-none');
        moduleControls.style.display = 'none';
        
        // Update the selected module card content
        const title = selectedCard.querySelector('.card-title');
        const body = selectedCard.querySelector('.card-text');
        
        if (title) title.textContent = module.name || 'No name';
        if (body) {
          body.innerHTML = `
            <p><strong>Code:</strong> ${module.code || 'N/A'}</p>
            <p><strong>Credits:</strong> ${module.credits || 'N/A'}</p>
            <p><strong>Year:</strong> ${module.year || 'N/A'}</p>
            <p><strong>Semester:</strong> ${module.semester || 'N/A'}</p>
            <p><strong>Program:</strong> ${module.program_name || `Program ${module.program_id || 'N/A'}`}</p>
          `;
        }
      } catch (e) {
        console.error('Error rendering selected module:', e);
        selectedCard.classList.add('d-none');
        moduleControls.style.display = '';
        localStorage.removeItem('selectedModule');
      }
    }
    
    // Initialize event listeners and load initial data
    function initEventListeners() {
      // Add event listeners for search input
      if (searchInput) {
        searchInput.addEventListener('input', function() {
          clearTimeout(debounceTimer);
          debounceTimer = setTimeout(() => {
            search = searchInput.value.trim();
            page = 1;
            loadModules();
          }, 300);
        });
      }

      // Add event listeners for filters
      if (yearFilter) {
        yearFilter.addEventListener('change', () => {
          year = yearFilter.value;
          page = 1;
          loadModules();
        });
      }

      if (semesterFilter) {
        semesterFilter.addEventListener('change', () => {
          semester = semesterFilter.value;
          page = 1;
          loadModules();
        });
      }

      // Add event listener for refresh button
      if (refreshBtn) {
        refreshBtn.addEventListener('click', (e) => {
          e.preventDefault();
          loadModules();
        });
      }

      // Add event listener for change module button
      if (changeBtn) {
        changeBtn.addEventListener('click', () => {
          localStorage.removeItem('selectedModule');
          renderSelectedModule();
          loadModules();
        });
      }

      // Add event delegation for select buttons
      if (modulesTbody) {
        modulesTbody.addEventListener('click', (e) => {
          const btn = e.target.closest('.select-btn');
          if (!btn) return;
          
          const moduleId = btn.dataset.id;
          const module = moduleMap.get(moduleId);
          
          if (module) {
            localStorage.setItem('selectedModule', JSON.stringify(module));
            renderSelectedModule();
          }
        });
      }

      // Add event listeners for sortable headers
      document.querySelectorAll('th.sortable').forEach(th => {
        th.addEventListener('click', () => {
          const field = th.dataset.field;
          if (sortField === field) {
            sortOrder = sortOrder === 'asc' ? 'desc' : 'asc';
          } else {
            sortField = field;
            sortOrder = 'asc';
          }
          loadModules();
        });
      });
    }

    // Initialize the application
    initEventListeners();
    loadModules();
    renderSelectedModule();
  }
  
  // Start the application if we're on the module selector page
  if (document.getElementById('modulesTbody')) {
    initApp();
  }
})();
</script>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>

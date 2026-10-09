/**
 * assets/js/calendar.js
 * -----------------------------------------------------------
 * PHASE: Reservation System (Calendar Widget)
 *
 * PARA SAAN ITO:
 * Ito yung buong "calendar" na nakikita sa Reserve Court page
 * at sa Admin Calendar — hindi lang basic na date picker, kundi
 * buong month-view calendar na kinukuha ang schedule data galing
 * sa calendar_data.php gamit ang AJAX (walang reload ng page).
 *
 * Kapag nag-click ng isang araw, kinukuha naman nito yung
 * detalyadong oras-per-oras na schedule galing sa slots_data.php.
 * -----------------------------------------------------------
 */

function initCourtCalendar(containerId, options) {
  options = options || {};
  const container = document.getElementById(containerId);
  if (!container) return; // kung walang container element sa page, huwag ituloy

  const baseUrl = container.dataset.baseUrl || '/';
  const onDaySelected = typeof options.onDaySelected === 'function' ? options.onDaySelected : function () {};

  // currentCourt = kung "Court 1" o "Court 2" ang kasalukuyang tinitingnan
  let currentCourt = options.court || null;

  // "today" na naka-set sa 00:00 (walang oras) para tama yung
  // pag-compare kung ang isang araw ay nakalipas na o hindi pa
  const today = new Date();
  today.setHours(0, 0, 0, 0);

  // viewYear / viewMonth = kung anong buwan/taon kasalukuyang
  // ipinapakita sa calendar (nagbabago 'to kapag pumindot ng
  // "previous" o "next" na buton)
  let viewYear = today.getFullYear();
  let viewMonth = today.getMonth(); // 0-indexed: 0=January, 11=December
  let selectedDate = options.initialDate || null; // "YYYY-MM-DD" na currently naka-select

  const monthNames = ['January','February','March','April','May','June','July','August','September','October','November','December'];
  const dayNames = ['Sun','Mon','Tue','Wed','Thu','Fri','Sat'];

  // -----------------------------------------------------------
  // GAWIN ANG LAYOUT NG CALENDAR (isang beses lang ito, tapos
  // yung mga laman/petsa na lang ang nagbabago sa render())
  // -----------------------------------------------------------
  container.innerHTML =
    '<div class="cc-calendar">' +
    '  <div class="cc-cal-header">' +
    '    <button type="button" class="cc-cal-nav" id="' + containerId + '_prev" aria-label="Previous month"><i class="bi bi-chevron-left"></i></button>' +
    '    <div class="cc-cal-title" id="' + containerId + '_title"></div>' +
    '    <button type="button" class="cc-cal-nav" id="' + containerId + '_next" aria-label="Next month"><i class="bi bi-chevron-right"></i></button>' +
    '  </div>' +
    '  <div class="cc-cal-weekdays">' +
         dayNames.map(function (d) { return '<div>' + d + '</div>'; }).join('') +
    '  </div>' +
    '  <div class="cc-cal-grid" id="' + containerId + '_grid"></div>' +
    '  <div class="cc-cal-legend">' +
    '    <span><i class="cc-dot cc-dot-available"></i> Available</span>' +
    '    <span><i class="cc-dot cc-dot-partial"></i> Partially Booked (some time remains)</span>' +
    '    <span><i class="cc-dot cc-dot-full"></i> Fully Booked</span>' +
    '    <span><i class="cc-dot cc-dot-closed"></i> Court Closed</span>' +
    '  </div>' +
    '</div>';

  const titleEl = document.getElementById(containerId + '_title');
  const gridEl  = document.getElementById(containerId + '_grid');
  const prevBtn = document.getElementById(containerId + '_prev');
  const nextBtn = document.getElementById(containerId + '_next');

  // -----------------------------------------------------------
  // ACTION: PINDOT NG "PREVIOUS MONTH" NA BUTON (<)
  // Ibinabawas ang 1 sa viewMonth. Kapag umabot sa -1 (ibig
  // sabihin lumagpas na sa January papuntang nakaraang taon),
  // ibinabalik natin sa 11 (December) at ibinabawas ang 1 sa
  // viewYear. Pagkatapos, tinatawag ang render() para muling
  // kunin ang schedule data ng bagong buwan.
  // -----------------------------------------------------------
  prevBtn.addEventListener('click', function () {
    viewMonth--;
    if (viewMonth < 0) { viewMonth = 11; viewYear--; } // lumipat sa December ng nakaraang taon
    render();
  });

  // -----------------------------------------------------------
  // ACTION: PINDOT NG "NEXT MONTH" NA BUTON (>)
  // Kabaligtaran ng taas: dinadagdagan ang viewMonth. Kapag
  // umabot sa 12 (ibig sabihin lumagpas na sa December papuntang
  // susunod na taon), ibinabalik sa 0 (January) at dinadagdagan
  // ang viewYear. Tapos muling kinukuha ang bagong schedule data.
  // -----------------------------------------------------------
  nextBtn.addEventListener('click', function () {
    viewMonth++;
    if (viewMonth > 11) { viewMonth = 0; viewYear++; } // lumipat sa January ng susunod na taon
    render();
  });

  // Nagdaragdag ng "0" sa unahan kung iisang digit lang (hal. 5 -> "05")
  // kailangan ito para tama yung format ng petsa: "2026-08-05" hindi "2026-8-5"
  function pad(n) { return n < 10 ? '0' + n : '' + n; }

  // Ginagawang "YYYY-MM" format ang kasalukuyang buwan/taon,
  // ito yung ipapadala natin sa calendar_data.php
  function monthKey() { return viewYear + '-' + pad(viewMonth + 1); }

  // -----------------------------------------------------------
  // ACTION: KUNIN ANG SCHEDULE DATA NG BUONG BUWAN (AJAX CALL)
  // Ipinapakita muna ang "Loading..." habang naghihintay ng
  // sagot mula sa server (calendar_data.php). Kapag dumating na
  // yung data, ipapasa ito sa drawGrid() para ipakita bilang
  // mga kahon (cells) na may kulay depende sa availability.
  // -----------------------------------------------------------
  function render() {
    titleEl.textContent = monthNames[viewMonth] + ' ' + viewYear;
    gridEl.innerHTML = '<div class="cc-cal-loading">Loading schedule…</div>';

    fetch(baseUrl + 'calendar_data.php?month=' + monthKey() + (currentCourt ? '&court=' + encodeURIComponent(currentCourt) : ''))
      .then(function (res) { return res.json(); })
      .then(function (data) { drawGrid(data.days || {}); })
      .catch(function () {
        // Kapag nagka-error (hal. walang internet, o may problema sa server)
        gridEl.innerHTML = '<div class="cc-cal-loading">Could not load the calendar. Please refresh.</div>';
      });
  }

  // -----------------------------------------------------------
  // ACTION: IGUHIT ANG BUONG GRID NG CALENDAR (mga kahon ng araw)
  // Ito yung pinaka-mahalagang function dito. Ginagawa nito ang
  // lahat ng kahon (cells) ng calendar para sa isang buwan,
  // kasama na yung mga "peeking" na araw ng nakaraan/susunod na
  // buwan (yung mga kulay-abo na petsa sa gilid).
  // -----------------------------------------------------------
  function drawGrid(daysData) {
    const firstOfMonth = new Date(viewYear, viewMonth, 1);
    const startWeekday = firstOfMonth.getDay(); // 0=Sunday - anong araw ng linggo nagsimula ang buwan
    const daysInMonth = new Date(viewYear, viewMonth + 1, 0).getDate(); // ilang araw meron ang buwan na 'to
    const daysInPrevMonth = new Date(viewYear, viewMonth, 0).getDate(); // ilang araw meron ang nakaraang buwan

    let html = '';
    // Buong bilang ng kahon na kailangan (palaging multiple ng 7,
    // dahil 7 araw bawat linggo)
    const totalCells = Math.ceil((startWeekday + daysInMonth) / 7) * 7;

    for (let i = 0; i < totalCells; i++) {
      const dayNum = i - startWeekday + 1;
      let cellDate, inMonth = true, label;

      // Tatlong posibleng senaryo bawat kahon:
      if (dayNum < 1) {
        // 1. Ito ay petsa pa ng NAKARAANG buwan (grayed out)
        label = daysInPrevMonth + dayNum;
        cellDate = new Date(viewYear, viewMonth - 1, label);
        inMonth = false;
      } else if (dayNum > daysInMonth) {
        // 2. Ito ay petsa na ng SUSUNOD na buwan (grayed out)
        label = dayNum - daysInMonth;
        cellDate = new Date(viewYear, viewMonth + 1, label);
        inMonth = false;
      } else {
        // 3. Normal na petsa, kabilang sa kasalukuyang buwan
        label = dayNum;
        cellDate = new Date(viewYear, viewMonth, dayNum);
      }

      // Ginagawang "YYYY-MM-DD" format ang petsa (ito yung gagamitin
      // bilang unique identifier ng bawat kahon)
      const iso = cellDate.getFullYear() + '-' + pad(cellDate.getMonth() + 1) + '-' + pad(cellDate.getDate());
      const isPast = cellDate < today; // lumipas na ba ang petsang ito?
      const isToday = cellDate.getTime() === today.getTime();
      const info = daysData[iso]; // schedule info galing sa server (kung meron)

      // -----------------------------------------------------
      // ANO ANG KULAY/STATUS NG KAHON NA ITO?
      // Default: "closed" — pero sa PRAKTIKA halos palagi namang
      // may "info" na galing sa server para sa bawat petsa ng
      // buwan (dahil bukas na ang bawat araw by default ngayon,
      // per weekly schedule — Mon-Fri 6-9PM, Sat-Sun 7AM-9PM).
      // "closed" na default lang mismo ang lalabas kapag WALANG
      // info entry — hal. sa mga "peeking" na petsa ng ibang
      // buwan sa gilid ng grid, na hindi naman kasama sa query.
      // -----------------------------------------------------
      let status = 'closed';
      if (isPast) {
        status = 'past'; // lumipas na, hindi na pwedeng piliin
      } else if (info) {
        // "available" (bukas pa lahat), "partial" (may ilang booked
        // na), "full" (puno na lahat ng oras), o "closed" (may
        // admin-announced court closure sa petsang ito, hal. bagyo)
        status = info.status;
      }

      // Pagsasama-sama ng CSS classes para sa itsura ng kahon
      const classes = ['cc-cal-cell'];
      if (!inMonth) classes.push('cc-cell-muted');       // kulay-abo kung ibang buwan
      if (isToday) classes.push('cc-cell-today');         // may outline kung "today"
      if (isPast) classes.push('cc-cell-disabled');       // hindi na pwedeng i-click kung lumipas na
      if (selectedDate === iso) classes.push('cc-cell-selected'); // naka-highlight kung ito ang pinili
      classes.push('cc-status-' + status);

      html += '<button type="button" class="' + classes.join(' ') + '" data-date="' + iso + '" ' + (isPast ? 'disabled' : '') + '>' +
              '<span class="cc-cell-num">' + label + '</span>' +
              (isPast ? '' : '<span class="cc-dot cc-dot-' + status + '"></span>') +
              '</button>';
    }

    gridEl.innerHTML = html;

    // -----------------------------------------------------
    // ACTION: PAG-CLICK NG ISANG KAHON (ARAW) SA CALENDAR
    // Kapag pinindot ang isang di-lumipas na araw, tinatanggal
    // muna natin ang "selected" style sa lahat ng kahon, tapos
    // ilalagay natin ito lang sa kahong kaka-click lang. Pagkatapos,
    // tatawagin natin yung onDaySelected callback (ibinigay galing
    // sa reserve.php o admin/calendar.php) para i-load yung
    // detalyadong oras-per-oras na schedule ng araw na 'to.
    // -----------------------------------------------------
    gridEl.querySelectorAll('.cc-cal-cell:not(.cc-cell-disabled)').forEach(function (cell) {
      cell.addEventListener('click', function () {
        gridEl.querySelectorAll('.cc-cal-cell').forEach(function (c) { c.classList.remove('cc-cell-selected'); });
        cell.classList.add('cc-cell-selected');
        selectedDate = cell.getAttribute('data-date');
        onDaySelected(selectedDate);
      });
    });
  }

  render(); // unang pag-guhit ng calendar pagka-load ng page

  // Mga function na pwedeng tawagin galing sa labas (reserve.php,
  // admin/calendar.php) para kontrolin ang calendar na 'to
  return {
    // Pumunta sa isang specific na petsa (ginagamit kapag
    // may pre-selected na date, hal. galing sa dating form)
    goToDate: function (iso) {
      const parts = iso.split('-').map(Number);
      viewYear = parts[0];
      viewMonth = parts[1] - 1;
      selectedDate = iso;
      render();
    },
    // Palitan ang court na tinitingnan (Court 1 <-> Court 2),
    // tapos i-refresh ang schedule data para sa bagong court
    setCourt: function (court) {
      currentCourt = court;
      render();
    }
  };
}

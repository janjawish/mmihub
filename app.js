// app.js
document.addEventListener('DOMContentLoaded', function () {
  // ===== NAVIGATION / HEADER =====
  const navToggle = document.querySelector('.nav-toggle');
  const mainNav   = document.querySelector('.main-nav');
  const header    = document.querySelector('.site-header');

  if (navToggle && mainNav) {
    navToggle.addEventListener('click', function () {
      mainNav.classList.toggle('nav-open');
      navToggle.classList.toggle('nav-open');
      document.body.classList.toggle('no-scroll');
    });
  }

  if (header) {
    window.addEventListener('scroll', function () {
      const scrolled = window.scrollY > 10;
      header.classList.toggle('header-scrolled', scrolled);
    });
  }

  // Helper pour le body quand un panneau est ouvert
  function addOverlayLock() {
    document.body.classList.add('overlay-open');
  }
  function removeOverlayLock() {
    document.body.classList.remove('overlay-open');
  }

  // =====================================================
  // ===============  MMI NOTES – PANEL "+"  =============
  // =====================================================
  const notesFab    = document.getElementById('notesFabBtn');
  const notesPanel  = document.getElementById('notesAddPanel');
  const notesClose  = document.getElementById('notesAddClose');
  const notesId     = document.getElementById('notesAssessmentId');
  const notesModule = document.getElementById('notesModuleSelect');
  const notesGrade  = document.getElementById('notesGradeInput');
  const notesKindRow = document.getElementById('notesKindRow');

  function openNotesPanel() {
    if (!notesPanel) return;
    notesPanel.classList.add('open');
    addOverlayLock();
  }

  function closeNotesPanel() {
    if (!notesPanel) return;
    notesPanel.classList.remove('open');
    removeOverlayLock();
  }

  if (notesFab && notesPanel) {
    notesFab.addEventListener('click', function (e) {
      e.preventDefault();
      // Reset du formulaire pour "nouvelle" note
      if (notesId) notesId.value = '';
      if (notesGrade) notesGrade.value = '';
      openNotesPanel();
    });
  }

  if (notesClose) {
    notesClose.addEventListener('click', function (e) {
      e.preventDefault();
      closeNotesPanel();
    });
  }

  if (notesPanel) {
    // clic sur le fond sombre => fermer
    notesPanel.addEventListener('click', function (e) {
      if (e.target === notesPanel) {
        closeNotesPanel();
      }
    });
  }

  // Boutons "Modifier" pour les notes
  const editBtns = document.querySelectorAll('.notes-edit-btn');
  if (editBtns.length && notesPanel) {
    const kindRadios = document.querySelectorAll('input[name="kind"]');

    editBtns.forEach(btn => {
      btn.addEventListener('click', function () {
        const id       = this.dataset.id || '';
        const moduleId = this.dataset.moduleId || '';
        const kind     = this.dataset.kind || '';
        const grade    = this.dataset.grade || '';

        if (notesId) notesId.value = id;
        if (notesModule && moduleId) {
          notesModule.value = moduleId;
          notesModule.dispatchEvent(new Event('change'));
        }
        if (notesGrade && grade !== '') {
          notesGrade.value = grade;
        }

        if (kindRadios && kind) {
          kindRadios.forEach(r => {
            r.checked = (r.value === kind);
          });
        }

        openNotesPanel();
      });
    });
  }

  // Affichage DS/TP/Unique en fonction du type de module
  if (notesModule && notesKindRow) {
    const dsWrap     = notesKindRow.querySelector('input[value="ds"]')?.parentElement;
    const tpWrap     = notesKindRow.querySelector('input[value="tp"]')?.parentElement;
    const unique     = notesKindRow.querySelector('input[value="unique"]');
    const uniqueWrap = unique ? unique.parentElement : null;

    function refreshKindVisibility() {
      const opt  = notesModule.options[notesModule.selectedIndex];
      const type = opt ? opt.dataset.type : null; // "ressource" ou "sae"

      if (!dsWrap || !tpWrap || !unique || !uniqueWrap) return;

      if (type === 'ressource') {
        // Ressource : DS / TP visibles
        dsWrap.style.display = '';
        tpWrap.style.display = '';
        uniqueWrap.style.display = 'none';
        if (!dsWrap.querySelector('input').checked &&
            !tpWrap.querySelector('input').checked) {
          dsWrap.querySelector('input').checked = true;
        }
      } else {
        // SAÉ / Portfolio : une seule note "unique"
        dsWrap.style.display = 'none';
        tpWrap.style.display = 'none';
        uniqueWrap.style.display = '';
        unique.checked = true;
      }
    }

    refreshKindVisibility();
    notesModule.addEventListener('change', refreshKindVisibility);
  }

  // =====================================================
  // ===============   MMI ABS – PANEL "+"   =============
  // =====================================================
  // On accepte soit des IDs (#absFabBtn / #absAddPanel),
  // soit des classes (.abs-fab / .abs-add-panel) au cas où.
  const absFab   = document.getElementById('absFabBtn') ||
                   document.querySelector('.abs-fab');
  const absPanel = document.getElementById('absAddPanel') ||
                   document.querySelector('.abs-add-panel');
  const absClose = document.getElementById('absAddClose') ||
                   (absPanel ? absPanel.querySelector('.abs-add-close') : null);

  function openAbsPanel() {
    if (!absPanel) return;
    absPanel.classList.add('open');
    addOverlayLock();
  }

  function closeAbsPanel() {
    if (!absPanel) return;
    absPanel.classList.remove('open');
    removeOverlayLock();
  }

  if (absFab && absPanel) {
    absFab.addEventListener('click', function (e) {
      e.preventDefault();
      // si tu as un champ caché pour l'id d'absence, tu peux le reset ici
      const absId = document.getElementById('absenceId');
      if (absId) absId.value = '';
      openAbsPanel();
    });
  }

  if (absClose) {
    absClose.addEventListener('click', function (e) {
      e.preventDefault();
      closeAbsPanel();
    });
  }

  if (absPanel) {
    absPanel.addEventListener('click', function (e) {
      if (e.target === absPanel) {
        closeAbsPanel();
      }
    });
  }
});

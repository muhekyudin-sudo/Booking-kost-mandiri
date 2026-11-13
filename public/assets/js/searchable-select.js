/*
 * Dropdown yang bisa diketik (searchable select).
 *
 * Cara pakai: tambahkan atribut  data-searchable  pada <select>.
 *   <select id="city_id" name="city_id" data-searchable> ... </select>
 *
 * <select> aslinya tetap ada (disembunyikan) dan tetap dikirim oleh form,
 * jadi kode PHP tidak perlu diubah. Kalau JavaScript mati, dropdown biasa
 * tetap berfungsi seperti sebelumnya.
 */
(function () {
  'use strict';

  function enhance(select) {
    var wrap = select.parentElement;

    // 1. Ambil semua pilihan dari <select>
    var items = Array.prototype.map.call(select.options, function (option) {
      return { value: option.value, label: option.text.trim() };
    });
    var allItem = items.filter(function (item) { return item.value === ''; })[0] || null; // "Semua kota"
    var choices = items.filter(function (item) { return item.value !== ''; });            // daftar kota

    // 2. Buat kotak ketik dan daftar rekomendasi
    var baseId = select.id || 'combo';
    var input = document.createElement('input');
    input.type = 'text';
    input.id = baseId + '_search';
    input.className = 'combo-input';
    input.autocomplete = 'off';
    input.spellcheck = false;
    input.placeholder = allItem ? allItem.label : 'Ketik untuk mencari...';
    input.setAttribute('role', 'combobox');
    input.setAttribute('aria-autocomplete', 'list');
    input.setAttribute('aria-expanded', 'false');
    input.setAttribute('aria-controls', baseId + '_list');

    var list = document.createElement('ul');
    list.id = baseId + '_list';
    list.className = 'combo-list';
    list.setAttribute('role', 'listbox');
    list.hidden = true;

    // Klik pada label ikut mengarah ke kotak ketik
    var label = select.id ? document.querySelector('label[for="' + select.id + '"]') : null;
    if (label) label.setAttribute('for', input.id);

    select.style.display = 'none';
    select.tabIndex = -1;
    select.setAttribute('aria-hidden', 'true');
    wrap.appendChild(input);
    wrap.appendChild(list);

    var rows = [];   // elemen <li> yang bisa dipilih
    var active = -1; // baris yang sedang disorot

    function labelOf(value) {
      var found = choices.filter(function (choice) { return choice.value === value; })[0];
      return found ? found.label : '';
    }

    // Isi kotak ketik sesuai pilihan <select> sekarang
    function sync() {
      input.value = select.value === '' ? '' : labelOf(select.value);
    }

    function setValue(value) {
      var changed = select.value !== value;
      select.value = value;
      sync();
      if (changed) select.dispatchEvent(new Event('change', { bubbles: true }));
    }

    // Tebalkan huruf yang cocok dengan ketikan (aman: memakai textContent, bukan innerHTML)
    function fill(li, text, query) {
      if (!query) { li.textContent = text; return; }
      var lower = text.toLowerCase();
      var position = 0;
      var index;
      while ((index = lower.indexOf(query, position)) !== -1) {
        if (index > position) li.appendChild(document.createTextNode(text.slice(position, index)));
        var mark = document.createElement('mark');
        mark.textContent = text.slice(index, index + query.length);
        li.appendChild(mark);
        position = index + query.length;
      }
      if (position < text.length) li.appendChild(document.createTextNode(text.slice(position)));
    }

    function addRow(item, query, id) {
      var li = document.createElement('li');
      li.id = baseId + '_opt' + id;
      li.setAttribute('role', 'option');
      li.dataset.value = item.value;
      if (item.value === select.value) {
        li.setAttribute('aria-selected', 'true');
        li.classList.add('is-current');
      }
      fill(li, item.label, query);
      list.appendChild(li);
      rows.push(li);
    }

    // Susun ulang daftar sesuai ketikan
    function render(rawQuery) {
      var query = rawQuery.trim().toLowerCase();
      while (list.firstChild) list.removeChild(list.firstChild);
      rows = [];

      var matches;
      if (query === '') {
        matches = choices.slice();
        if (allItem) addRow(allItem, '', rows.length);
      } else {
        // Yang diawali ketikan ditaruh paling atas, lalu yang mengandung ketikan di tengah
        var starts = choices.filter(function (c) { return c.label.toLowerCase().indexOf(query) === 0; });
        var inside = choices.filter(function (c) { return c.label.toLowerCase().indexOf(query) > 0; });
        matches = starts.concat(inside);
      }
      matches.forEach(function (choice) { addRow(choice, query, rows.length); });

      if (rows.length === 0) {
        var empty = document.createElement('li');
        empty.className = 'is-empty';
        empty.textContent = 'Tidak ditemukan';
        list.appendChild(empty);
      }

      // Sorotan awal: hasil pertama saat mengetik, atau pilihan saat ini saat daftar dibuka penuh
      var start = 0;
      if (query === '') {
        rows.forEach(function (row, i) { if (row.dataset.value === select.value) start = i; });
      }
      setActive(rows.length ? start : -1);
    }

    function setActive(index) {
      rows.forEach(function (row) { row.classList.remove('is-active'); });
      active = index;
      if (index >= 0 && rows[index]) {
        rows[index].classList.add('is-active');
        input.setAttribute('aria-activedescendant', rows[index].id);
        if (rows[index].scrollIntoView) rows[index].scrollIntoView({ block: 'nearest' });
      } else {
        input.removeAttribute('aria-activedescendant');
      }
    }

    function open() {
      list.hidden = false;
      input.setAttribute('aria-expanded', 'true');
    }

    function close() {
      list.hidden = true;
      input.setAttribute('aria-expanded', 'false');
      input.removeAttribute('aria-activedescendant');
    }

    function choose(value) {
      setValue(value);
      close();
    }

    // Saat kotak kehilangan fokus: terima ketikan yang persis sama dengan nama kota,
    // kosong = "Semua", selain itu kembali ke pilihan sebelumnya.
    function commit() {
      var typed = input.value.trim().toLowerCase();
      if (typed === '' && allItem) {
        setValue('');
      } else {
        var exact = choices.filter(function (c) { return c.label.toLowerCase() === typed; })[0];
        if (exact) setValue(exact.value); else sync();
      }
      close();
    }

    input.addEventListener('focus', function () {
      input.select();       // ketikan baru langsung menimpa teks lama
      render('');
      open();
    });
    input.addEventListener('click', function () {
      if (list.hidden) { render(''); open(); }
    });
    input.addEventListener('input', function () {
      render(input.value);
      open();
    });
    input.addEventListener('keydown', function (event) {
      if (event.key === 'ArrowDown') {
        event.preventDefault();
        if (list.hidden) { render(input.value); open(); }
        else if (rows.length) setActive(Math.min(active + 1, rows.length - 1));
      } else if (event.key === 'ArrowUp') {
        event.preventDefault();
        if (rows.length) setActive(Math.max(active - 1, 0));
      } else if (event.key === 'Enter') {
        if (!list.hidden) {
          event.preventDefault(); // Enter memilih rekomendasi, belum mengirim form
          if (active >= 0 && rows[active]) choose(rows[active].dataset.value);
        }
      } else if (event.key === 'Escape') {
        sync();
        close();
      }
    });
    input.addEventListener('blur', commit);

    // mousedown (bukan click) supaya kotak ketik tidak kehilangan fokus lebih dulu
    list.addEventListener('mousedown', function (event) {
      event.preventDefault();
      var li = event.target.closest ? event.target.closest('li[data-value]') : null;
      if (li) choose(li.dataset.value);
    });

    // Tombol "Back" browser bisa memulihkan nilai <select>; samakan isi kotak ketik
    window.addEventListener('pageshow', sync);

    sync();
  }

  function init() {
    Array.prototype.forEach.call(document.querySelectorAll('select[data-searchable]'), enhance);
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', init);
  } else {
    init();
  }
})();

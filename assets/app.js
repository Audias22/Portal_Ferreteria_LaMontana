// Mostrar u ocultar contraseña (oculta por defecto).
document.querySelectorAll('input[type="password"]').forEach(function (input) {
  var wrap = document.createElement('span');
  wrap.className = 'pw-wrap';
  input.parentNode.insertBefore(wrap, input);
  wrap.appendChild(input);
  var btn = document.createElement('button');
  btn.type = 'button';
  btn.className = 'pw-toggle';
  btn.textContent = 'Ver';
  btn.setAttribute('aria-label', 'Mostrar contraseña');
  btn.addEventListener('click', function () {
    var show = input.type === 'password';
    input.type = show ? 'text' : 'password';
    btn.textContent = show ? 'Ocultar' : 'Ver';
    btn.setAttribute('aria-label', show ? 'Ocultar contraseña' : 'Mostrar contraseña');
    input.focus();
  });
  wrap.appendChild(btn);
  if (input.form) input.form.addEventListener('submit', function () { input.type = 'password'; });
});

// Confirmación antes de acciones delicadas (por ejemplo, anular una venta).
document.addEventListener('click', function (e) {
  var b = e.target.closest('[data-confirm]');
  if (b && !window.confirm(b.getAttribute('data-confirm'))) e.preventDefault();
  if (e.target.closest('[data-print]')) window.print();
});

// Nueva venta: subtotales en pantalla y botón para agregar líneas.
(function () {
  var body = document.getElementById('lines');
  if (!body) return;
  function fmt(n) { return 'Q ' + n.toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 }); }
  function recalc() {
    var total = 0;
    body.querySelectorAll('tr').forEach(function (tr) {
      var sel = tr.querySelector('.js-prod'), cant = tr.querySelector('.js-cant'), sub = tr.querySelector('.js-sub');
      var opt = sel.options[sel.selectedIndex];
      var precio = opt && opt.dataset.precio ? parseFloat(opt.dataset.precio) : 0;
      var s = precio * (parseInt(cant.value, 10) || 0);
      sub.textContent = precio ? fmt(s) : '—';
      total += s;
    });
    document.getElementById('total').textContent = fmt(total);
  }
  body.addEventListener('change', recalc);
  body.addEventListener('input', recalc);
  document.getElementById('add-line').addEventListener('click', function () {
    var tr = body.querySelector('tr').cloneNode(true);
    tr.querySelector('.js-prod').selectedIndex = 0;
    tr.querySelector('.js-cant').value = 1;
    tr.querySelector('.js-sub').textContent = '—';
    body.appendChild(tr);
  });
  recalc();
})();

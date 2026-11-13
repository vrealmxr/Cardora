(() => {
  if (document.getElementById('cardora-scanner-link')) return;
  const link = document.createElement('a');
  link.id = 'cardora-scanner-link'; link.href = '/scanner';
  link.textContent = 'Cardora-1 Scan';
  link.setAttribute('aria-label', 'Cardora-1 card scanner');
  link.style.cssText = 'position:fixed;bottom:24px;right:24px;z-index:40;border:1px solid #d6b35a;border-radius:999px;background:#17212b;color:#f5dda0;padding:12px 18px;font:600 14px system-ui;box-shadow:0 4px 16px #0002;text-decoration:none';
  document.body.append(link);
})();
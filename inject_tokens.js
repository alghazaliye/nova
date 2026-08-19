(async () => {
  const base = location.origin.replace(/\/$/, '');
  const api = base + '/web_app/api';
  const headers = {'Content-Type': 'application/json'};

  async function tokenOf(phone, uuid) {
    const r1 = await fetch(api + '/v1/auth/login', {method:'POST', headers, body: JSON.stringify({phone, device_uuid: uuid, app_version:'3.7.0', platform:'web', os_name:'Web', os_version:'browser'})});
    const d1 = await r1.json();
    if (!d1.success) throw new Error('login req failed: ' + JSON.stringify(d1));
    const r2 = await fetch(api + '/v1/auth/verify-otp', {method:'POST', headers, body: JSON.stringify({phone, otp:'123456', device_uuid: uuid, app_version:'3.7.0', platform:'web', os_name:'Web', os_version:'browser'})});
    const d2 = await r2.json();
    if (!d2.success) throw new Error('verify failed: ' + JSON.stringify(d2));
    return {token: d2.data.token, user: d2.data.user};
  }

  const u1 = await tokenOf('+966501234567', 'web_ahmad');
  const u2 = await tokenOf('+966502345678', 'web_salem');

  // تخزين توكن أحمد في هذه النافذة
  localStorage.setItem('flutter_secure_storage_token', u1.token);
  localStorage.setItem('token', u1.token);

  return JSON.stringify({
    ahmad: u1.user.name + ' id=' + u1.user.id + ' tok=' + u1.token.slice(0,16),
    salem: u2.user.name + ' id=' + u2.user.id + ' tok=' + u2.token.slice(0,16),
  });
})()

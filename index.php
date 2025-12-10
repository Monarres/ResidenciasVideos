<?php 
session_start(); 
if (isset($_SESSION['id_usuario'])) {
  header('Location: ' . ($_SESSION['rol'] === 'admin' ? 'admin/dashboard.php' : 'usuario/dashboard.php'));
  exit;
} 
?>
<!doctype html>
<html lang="es">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<meta http-equiv="X-UA-Compatible" content="IE=edge">
<title>Login - eLearning</title>
<!-- Meta tags anti-caché -->
<meta http-equiv="Cache-Control" content="no-cache, no-store, must-revalidate">
<meta http-equiv="Pragma" content="no-cache">
<meta http-equiv="Expires" content="0">
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
<style>
* {
  margin: 0;
  padding: 0;
  box-sizing: border-box;
}

body {
  background: linear-gradient(135deg, #f5c6d9, #e8b4d4);
  font-family: 'Poppins', sans-serif;
  min-height: 100vh;
  display: flex;
  align-items: center;
  justify-content: center;
  padding: 20px;
  position: relative;
  overflow: hidden;
}

/* Contenedor de burbujas */
.bubbles {
  position: fixed;
  top: 0;
  left: 0;
  width: 100%;
  height: 100%;
  z-index: 0;
  overflow: hidden;
  pointer-events: none;
}

.bubble {
  position: absolute;
  bottom: -150px;
  background: rgba(155, 124, 184, 0.5);
  border-radius: 50%;
  opacity: 0.7;
  animation: rise linear infinite;
  box-shadow: 0 0 20px rgba(155, 124, 184, 0.3);
}

.bubble:nth-child(1) {
  width: 80px;
  height: 80px;
  left: 10%;
  animation-duration: 15s;
  background: rgba(139, 92, 172, 0.6);
  animation-delay: 0s;
}

.bubble:nth-child(2) {
  width: 60px;
  height: 60px;
  left: 20%;
  animation-duration: 12s;
  background: rgba(155, 124, 184, 0.55);
  animation-delay: 2s;
}

.bubble:nth-child(3) {
  width: 100px;
  height: 100px;
  left: 35%;
  animation-duration: 18s;
  background: rgba(123, 104, 238, 0.5);
  animation-delay: 4s;
}

.bubble:nth-child(4) {
  width: 50px;
  height: 50px;
  left: 50%;
  animation-duration: 14s;
  background: rgba(147, 112, 219, 0.6);
  animation-delay: 0s;
}

.bubble:nth-child(5) {
  width: 70px;
  height: 70px;
  left: 55%;
  animation-duration: 16s;
  background: rgba(138, 43, 226, 0.45);
  animation-delay: 3s;
}

.bubble:nth-child(6) {
  width: 90px;
  height: 90px;
  left: 65%;
  animation-duration: 13s;
  background: rgba(155, 124, 184, 0.6);
  animation-delay: 5s;
}

.bubble:nth-child(7) {
  width: 65px;
  height: 65px;
  left: 75%;
  animation-duration: 17s;
  background: rgba(139, 92, 172, 0.55);
  animation-delay: 1s;
}

.bubble:nth-child(8) {
  width: 85px;
  height: 85px;
  left: 85%;
  animation-duration: 19s;
  background: rgba(123, 104, 238, 0.5);
  animation-delay: 6s;
}

.bubble:nth-child(9) {
  width: 55px;
  height: 55px;
  left: 90%;
  animation-duration: 11s;
  background: rgba(147, 112, 219, 0.6);
  animation-delay: 2s;
}

.bubble:nth-child(10) {
  width: 75px;
  height: 75px;
  left: 5%;
  animation-duration: 20s;
  background: rgba(138, 43, 226, 0.5);
  animation-delay: 7s;
}

@keyframes rise {
  0% {
    bottom: -150px;
    transform: translateX(0) scale(1);
    opacity: 0.7;
  }
  50% {
    transform: translateX(100px) scale(1.1);
    opacity: 0.9;
  }
  100% {
    bottom: 110vh;
    transform: translateX(-50px) scale(0.8);
    opacity: 0;
  }
}

.login-container {
  background: #fff;
  border-radius: 15px;
  box-shadow: 0 0 30px rgba(0,0,0,0.15);
  display: flex;
  overflow: hidden;
  width: 850px;
  max-width: 95%;
  margin: auto;
  position: relative;
  z-index: 1;
  animation: fadeIn 0.5s ease-in;
}

@keyframes fadeIn {
  from {
    opacity: 0;
    transform: translateY(20px);
  }
  to {
    opacity: 1;
    transform: translateY(0);
  }
}

.login-left {
  background: linear-gradient(135deg, #9b7cb8, #b893cc);
  color: white;
  width: 45%;
  display: flex;
  align-items: center;
  justify-content: center;
  flex-direction: column;
  padding: 40px 20px;
  text-align: center;
}

.login-left h2 {
  font-size: 28px;
  font-weight: 600;
  margin-bottom: 10px;
}

.login-left p {
  font-size: 14px;
  opacity: 0.9;
  margin-bottom: 20px;
}

.login-left img {
  width: 80%;
  max-width: 240px;
  margin-top: 20px;
  animation: float 3s ease-in-out infinite;
}

@keyframes float {
  0%, 100% {
    transform: translateY(0);
  }
  50% {
    transform: translateY(-10px);
  }
}

.login-right {
  width: 55%;
  padding: 50px;
  display: flex;
  flex-direction: column;
  justify-content: center;
}

.login-right h3 {
  color: #9b7cb8;
  font-weight: 600;
  margin-bottom: 30px;
  text-align: center;
  font-size: 26px;
}

.form-control {
  border-radius: 25px;
  padding: 12px 20px;
  border: 1px solid #ddd;
  transition: all 0.3s;
  font-size: 15px;
}

.form-control:focus {
  border-color: #9b7cb8;
  box-shadow: 0 0 0 0.2rem rgba(155, 124, 184, 0.25);
}

.btn-login {
  background: linear-gradient(135deg, #f5a3c7, #9b7cb8);
  border: none;
  color: white;
  font-weight: 500;
  border-radius: 25px;
  padding: 12px;
  transition: all 0.3s;
  font-size: 16px;
  cursor: pointer;
}

.btn-login:hover {
  background: linear-gradient(135deg, #9b7cb8, #f5a3c7);
  transform: translateY(-2px);
  box-shadow: 0 5px 15px rgba(155, 124, 184, 0.4);
}

.btn-login:active {
  transform: translateY(0);
}

a {
  text-decoration: none;
  color: #9b7cb8;
  font-size: 0.9em;
}

a:hover {
  text-decoration: underline;
}

#msg {
  margin-bottom: 15px;
}

.alert {
  border-radius: 15px;
  padding: 12px;
  font-size: 14px;
}

/* ============================================
   RESPONSIVE DESIGN
   ============================================ */

/* Tablets y móviles grandes (768px y menores) */
@media screen and (max-width: 768px) {
  body {
    padding: 10px;
  }
  
  .login-container {
    flex-direction: column;
    width: 100%;
    max-width: 500px;
  }
  
  .login-left {
    width: 100%;
    padding: 30px 20px;
    order: 1;
  }
  
  .login-left h2 {
    font-size: 24px;
  }
  
  .login-left p {
    font-size: 13px;
  }
  
  .login-left img {
    max-width: 180px;
    margin-top: 15px;
  }
  
  .login-right {
    width: 100%;
    padding: 30px;
    order: 2;
  }
  
  .login-right h3 {
    font-size: 22px;
    margin-bottom: 25px;
  }
  
  /* Reducir tamaño de burbujas en tablets */
  .bubble {
    transform: scale(0.8);
  }
}

/* Móviles pequeños (480px y menores) */
@media screen and (max-width: 480px) {
  body {
    padding: 5px;
  }
  
  .login-container {
    border-radius: 10px;
    max-width: 100%;
  }
  
  .login-left {
    padding: 25px 15px;
  }
  
  .login-left h2 {
    font-size: 20px;
  }
  
  .login-left p {
    font-size: 12px;
  }
  
  .login-left img {
    max-width: 150px;
    margin-top: 10px;
  }
  
  .login-right {
    padding: 25px 20px;
  }
  
  .login-right h3 {
    font-size: 20px;
    margin-bottom: 20px;
  }
  
  .form-control {
    padding: 10px 18px;
    font-size: 14px;
  }
  
  .btn-login {
    padding: 10px;
    font-size: 15px;
  }
  
  .mb-3 {
    margin-bottom: 15px !important;
  }
  
  /* Reducir más las burbujas en móvil */
  .bubble {
    transform: scale(0.6);
    opacity: 0.5;
  }
  
  .alert {
    font-size: 13px;
    padding: 10px;
  }
}

/* Móviles muy pequeños (360px y menores) */
@media screen and (max-width: 360px) {
  .login-left {
    padding: 20px 10px;
  }
  
  .login-left h2 {
    font-size: 18px;
  }
  
  .login-left img {
    max-width: 120px;
  }
  
  .login-right {
    padding: 20px 15px;
  }
  
  .login-right h3 {
    font-size: 18px;
  }
  
  .form-control {
    padding: 9px 15px;
    font-size: 13px;
  }
  
  .btn-login {
    font-size: 14px;
  }
}

/* Landscape móviles */
@media screen and (max-height: 500px) and (orientation: landscape) {
  body {
    padding: 10px;
    align-items: flex-start;
  }
  
  .login-container {
    flex-direction: row;
    margin: 10px auto;
  }
  
  .login-left {
    width: 40%;
    padding: 20px 15px;
  }
  
  .login-left h2 {
    font-size: 18px;
    margin-bottom: 5px;
  }
  
  .login-left p {
    font-size: 11px;
    margin-bottom: 10px;
  }
  
  .login-left img {
    max-width: 100px;
    margin-top: 10px;
  }
  
  .login-right {
    width: 60%;
    padding: 20px;
  }
  
  .login-right h3 {
    font-size: 18px;
    margin-bottom: 15px;
  }
  
  .form-control {
    padding: 8px 15px;
  }
  
  .btn-login {
    padding: 8px;
  }
  
  .mb-3 {
    margin-bottom: 10px !important;
  }
}

/* Pantallas grandes (1200px y mayores) */
@media screen and (min-width: 1200px) {
  .login-container {
    width: 900px;
  }
  
  .login-left h2 {
    font-size: 30px;
  }
  
  .login-left img {
    max-width: 260px;
  }
  
  .login-right {
    padding: 60px;
  }
  
  .login-right h3 {
    font-size: 28px;
  }
}
</style>
</head>
<body>

<!-- Burbujas animadas -->
<div class="bubbles">
  <div class="bubble"></div>
  <div class="bubble"></div>
  <div class="bubble"></div>
  <div class="bubble"></div>
  <div class="bubble"></div>
  <div class="bubble"></div>
  <div class="bubble"></div>
  <div class="bubble"></div>
  <div class="bubble"></div>
  <div class="bubble"></div>
</div>
  
<div class="login-container">
  <div class="login-left">
    <h2>Baby ballet Marbet®</h2>
    <p>International Dancing Corporation</p>
    <img src="assets/images/Mascotas_1.png" alt="login illustration">
  </div>
  <div class="login-right">
    <h3>Login</h3>
    <div id="msg"></div>
    <form id="loginForm" autocomplete="off">
      <div class="mb-3">
        <input name="email" type="email" class="form-control" placeholder="Username" required autocomplete="off" value="">
      </div>
      <div class="mb-3">
        <input name="password" type="password" class="form-control" placeholder="Password" required autocomplete="new-password" value="">
      </div>
      <button class="btn-login w-100" type="submit">Login</button>
    </form>
  </div>
</div>

<script>
document.getElementById('loginForm').addEventListener('submit', async e=>{
  e.preventDefault();
  const fd = new FormData(e.target);
  const res = await fetch('login.php',{method:'POST',body:fd});
  const data = await res.json();
  const msg = document.getElementById('msg');
  if(data.success){
    msg.innerHTML = `<div class="alert alert-success">${data.message}</div>`;
    setTimeout(()=> location.href = data.redirect, 700);
  } else {
    msg.innerHTML = `<div class="alert alert-danger">${data.message}</div>`;
  }
});

// Limpiar formulario al cargar la página
window.onload = function() {
    const form = document.getElementById('loginForm');
    form.reset();
    // Limpiar cada campo individualmente
    document.querySelectorAll('input').forEach(input => {
        input.value = '';
    });
};

// Limpiar cuando viene del historial (botón atrás)
window.addEventListener('pageshow', function(event) {
    if (event.persisted || (window.performance && window.performance.navigation.type === 2)) {
        const form = document.getElementById('loginForm');
        form.reset();
        document.querySelectorAll('input').forEach(input => {
            input.value = '';
        });
    }
});
</script>
</body>
</html>
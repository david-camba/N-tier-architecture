<?php
echo '
<div style="display:flex; flex-direction:column; justify-content:center; align-items:center; height:100vh; text-align:center;">
    <h1 style="font-size:2.5rem; margin-bottom:10px;">¡Hola! Gracias por venir</h1>
    <h1 style="font-size:2.5rem; margin-bottom:20px;">Hello! Thanks for coming.</h1>
    <a href="/app" style="
        font-size:2rem;
        color:#007BFF;
        text-decoration:none;
        transition:color 0.3s ease;
        margin-top: 50px;
    " onmouseover="this.style.color=\'#0056b3\'" onmouseout="this.style.color=\'#007BFF\'">
        Volver a la aplicación principal / Back to main app
    </a>
</div>';
exit();
<?php
class ErrorController_Base extends Controller
{
    public function __construct(TranslatorService $translator)
    {
        $this->translator = $translator;
    }
    
    public function showNotFound($url)
    {
        $errorMessage = (string) $this->translate('not_found_message', [$url]);
        $goBack = (string) $this->translate('go_back_main');

        echo '
        <div style="display:flex; flex-direction:column; justify-content:center; align-items:center; height:100vh; text-align:center;">
            <h1 style="font-size:2.5rem; margin-bottom:20px;">' . htmlspecialchars($errorMessage) . '</h1>
            <a href="/app" style="
                font-size:1.5rem;
                color:#007BFF;
                text-decoration:none;
                transition:color 0.3s ease;
            " onmouseover="this.style.color=\'#0056b3\'" onmouseout="this.style.color=\'#007BFF\'">
                ' . htmlspecialchars($goBack) . '
            </a>
        </div>';
        exit();
    }

}
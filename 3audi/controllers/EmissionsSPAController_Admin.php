<?php
class EmissionsSPAController_Admin_3Audi extends Controller
{
    public function __construct(TranslatorService $translator)
    {
        $this->translator = $translator;
    }

    public function show()
    {
        echo '
            <div style="display:flex;flex-direction:column;justify-content:center;align-items:center;min-height:100vh;font-family:Arial,sans-serif;background:#f9f9f9;color:#333;text-align:center;padding:20px;">
                
                <a href="/app" style="display:inline-block;margin-bottom:25px;padding:12px 24px;background:#007bff;color:#fff;text-decoration:none;border-radius:8px;font-size:18px;transition:background 0.3s ease;">
                    ' . $this->translate('go_back_main') . '
                </a>
                
                <div style="max-width:750px;background:#fff;padding:30px;border-radius:12px;box-shadow:0 4px 20px rgba(0,0,0,0.1);">
                    <h1 style="font-size:28px;margin-bottom:15px;color:#222;">' . $this->translate('exclusive_manager_controller_title') . '</h1>
                    
                    <p style="font-size:18px;line-height:1.6;margin-bottom:20px;color:#555;">
                        ' . $this->translate('exclusive_manager_controller_description') . '
                    </p>
                    
                    <p style="font-size:18px;line-height:1.6;margin-bottom:0;color:green;font-weight:bold;">
                        ' . $this->translate('audi_emissions_priority') . '
                    </p>
                </div>
                
                <style>
                    a:hover {
                        background:#0056b3;
                    }
                </style>
            </div>
        ';

        exit();
    }
}
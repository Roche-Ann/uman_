// main.js — LGU Urban Planning System

// =============================================================================
// 1. GLOBAL UTILITY FUNCTIONS
// =============================================================================

// Custom Alert Modal
function showAlert(type, title, msg) {
    const config = {
        error:   { icon: '✕', bg: 'error',   color: '#ef4444' },
        warning: { icon: '!', bg: 'warning',  color: '#f59e0b' },
        success: { icon: '✓', bg: 'success',  color: '#22c55e' },
        info:    { icon: 'i', bg: 'info',     color: '#3b82f6' },
    };
    const c = config[type] || config.info;

    // Inject a minimal modal if the full one from header.php isn't present.
    if (!document.getElementById('customAlertOverlay')) {
        document.body.insertAdjacentHTML('beforeend', `
            <div id="customAlertOverlay" style="
                display:none; position:fixed; inset:0; z-index:99999;
                background:rgba(0,0,0,.5); align-items:center; justify-content:center;">
                <div style="
                    background:#fff; border-radius:12px; padding:32px 28px;
                    max-width:380px; width:90%; text-align:center;
                    box-shadow:0 8px 32px rgba(0,0,0,.2);">
                    <div id="customAlertIconWrap" style="font-size:2rem; margin-bottom:8px;">
                        <span id="customAlertIcon"></span>
                    </div>
                    <h5 id="customAlertTitle" style="margin:0 0 8px; font-weight:700; color:#111;"></h5>
                    <p  id="customAlertMsg"   style="margin:0 0 20px; color:#555; font-size:.95rem;"></p>
                    <button onclick="closeAlert(true)"
                        style="background:#6366f1;color:#fff;border:none;border-radius:8px;
                               padding:8px 24px;font-weight:600;cursor:pointer;">OK</button>
                </div>
            </div>`);
    }

    const overlay = document.getElementById('customAlertOverlay');
    const iconWrap = document.getElementById('customAlertIconWrap');
    const iconEl   = document.getElementById('customAlertIcon');

    if (iconWrap) {
        iconWrap.className     = `alert-icon-wrap ${c.bg}`;
        iconEl.textContent     = c.icon;
        iconEl.style.color     = c.color;
        iconEl.style.fontWeight  = '700';
        iconEl.style.fontSize  = '1.5rem';
        iconEl.style.fontFamily = 'monospace';
    }

    document.getElementById('customAlertTitle').textContent = title;
    document.getElementById('customAlertMsg').textContent   = msg;

    // Support both CSS-class-based and inline-style-based overlays.
    overlay.style.display = 'flex';
    overlay.classList.add('show');
}

function closeAlert() {
    const overlay = document.getElementById('customAlertOverlay');
    if (overlay) {
        overlay.classList.remove('show');
        overlay.style.display = 'none';
    }
}

// Password Visibility Toggle
function togglePassword(inputId, iconId) {
    const input = document.getElementById(inputId);
    const icon  = document.getElementById(iconId);
    if (input && icon) {
        const isPass = input.type === 'password';
        input.type = isPass ? 'text' : 'password';
        icon.classList.toggle('bi-eye',       !isPass);
        icon.classList.toggle('bi-eye-slash',  isPass);
    }
}

// Multi-step Form Navigation
function nextStep(step) {
    const currentStep    = document.querySelector('.form-step.active');
    const currentStepNum = currentStep ? parseInt(currentStep.id.replace('step', '')) : 0;

    if (step > currentStepNum) {
        const inputs   = currentStep.querySelectorAll('input[required], select[required]');
        let   allValid = true;

        inputs.forEach(input => {
            if (!input.value.trim()) {
                allValid = false;
                input.classList.add('is-invalid');
            } else {
                input.classList.remove('is-invalid');
            }
        });

        if (!allValid) {
            showAlert('warning', 'Incomplete Fields', 'Please fill in all required fields before proceeding.');
            return;
        }

        // Password validation for Step 2
        const regPass    = document.getElementById('reg_password');
        const confirmPass = document.getElementById('confirm_password');
        if (currentStepNum === 2 && regPass) {
            const pass = regPass.value;
            if (pass.length < 8 || !/[A-Z]/.test(pass) || !/[0-9]/.test(pass)) {
                showAlert('warning', 'Weak Password', 'Password must be at least 8 characters with an uppercase letter and a number.');
                return;
            }
            if (pass !== confirmPass.value) {
                showAlert('error', 'Password Mismatch', 'Passwords do not match. Please try again.');
                confirmPass.classList.add('is-invalid');
                return;
            }
        }
    }

    document.querySelectorAll('.form-step').forEach(el => el.classList.remove('active'));
    const target = document.getElementById(`step${step}`);
    if (target) target.classList.add('active');

    document.querySelectorAll('.dot').forEach((dot, idx) => {
        dot.classList.toggle('active', idx + 1 === step);
    });

    document.querySelector('.register-card')?.scrollIntoView({ behavior: 'smooth', block: 'start' });
}

// ID Upload Toggle
function toggleUploads() {
    const idType       = document.getElementById('id_type');
    const uploadSection = document.getElementById('upload-section');
    const idFront      = document.querySelector('input[name="id_front"]');
    const idBack       = document.querySelector('input[name="id_back"]');

    if (idType && uploadSection) {
        if (idType.value) {
            uploadSection.style.display = 'block';
            idFront.required = true;
            idBack.required  = true;
        } else {
            uploadSection.style.display = 'none';
            idFront.required = false;
            idBack.required  = false;
        }
    }
}

// File Validation
function validateFileSize(input) {
    if (input.files?.[0] && input.files[0].size / 1024 / 1024 > 2) {
        showAlert('warning', 'File Too Large', 'Maximum allowed file size is 2MB. Please choose a smaller file.');
        input.value = '';
    }
}

// =============================================================================
// 2. CORE LOGIC (ON DOM LOAD)
// =============================================================================
document.addEventListener('DOMContentLoaded', () => {

    // --- Selectors ---
    const html        = document.documentElement;
    const darkModeBtn = document.getElementById('darkModeBtn');
    const btnEn       = document.getElementById('btn-en');
    const btnTl       = document.getElementById('btn-tl');

    // -------------------------------------------------------------------------
    // Theme Management
    // -------------------------------------------------------------------------
    function applyTheme(theme) {
        html.setAttribute('data-bs-theme', theme);
        localStorage.setItem('theme', theme);

        if (darkModeBtn) {
            darkModeBtn.innerHTML = theme === 'dark'
                ? '<i class="bi bi-sun fs-5"></i>'
                : '<i class="bi bi-moon-stars fs-5"></i>';
        }
    }

    darkModeBtn?.addEventListener('click', e => {
        e.preventDefault();
        applyTheme(html.getAttribute('data-bs-theme') === 'dark' ? 'light' : 'dark');
    });

    // -------------------------------------------------------------------------
    // Language Management
    // -------------------------------------------------------------------------
    function changeLanguage(lang) {
        btnEn?.classList.toggle('active', lang === 'en');
        btnTl?.classList.toggle('active', lang === 'tl');

        document.querySelectorAll('[data-en]').forEach(el => {
            const trans = el.getAttribute(`data-${lang}`);
            if (trans) el.textContent = trans;
        });

        document.querySelectorAll('[data-en-placeholder]').forEach(input => {
            const transPlaceholder = input.getAttribute(`data-${lang}-placeholder`);
            if (transPlaceholder) input.setAttribute('placeholder', transPlaceholder);
        });

        localStorage.setItem('preferredLang', lang);
    }

    btnEn?.addEventListener('click', () => changeLanguage('en'));
    btnTl?.addEventListener('click', () => changeLanguage('tl'));

    // -------------------------------------------------------------------------
    // Initialization
    // -------------------------------------------------------------------------
    applyTheme(localStorage.getItem('theme') || 'light');
    changeLanguage(localStorage.getItem('preferredLang') || 'en');

    // -------------------------------------------------------------------------
    // Custom Alert (Login / Register error pass-through from PHP)
    // -------------------------------------------------------------------------
    const alertOverlay = document.getElementById('customAlertOverlay');
    if (alertOverlay) {
        alertOverlay.addEventListener('click', function (e) {
            if (e.target === this) closeAlert();
        });

        if (alertOverlay.dataset.loginError)    showAlert('error',   'Login Failed',        alertOverlay.dataset.loginError);
        if (alertOverlay.dataset.registerError) showAlert('error',   'Registration Error',  alertOverlay.dataset.registerError);
        if (alertOverlay.dataset.registerSuccess) showAlert('success', 'OTP Resent',         alertOverlay.dataset.registerSuccess);
    }

    // Bootstrap Alert Auto-dismiss (5 s)
    setTimeout(() => {
        document.querySelectorAll('.alert').forEach(alert => {
            if (typeof bootstrap !== 'undefined' && bootstrap.Alert) {
                new bootstrap.Alert(alert).close();
            }
        });
    }, 5000);

    // -------------------------------------------------------------------------
    // Password Strength Meter
    // -------------------------------------------------------------------------
    const regPassInput = document.getElementById('reg_password');
    if (regPassInput) {
        regPassInput.addEventListener('input', function () {
            const password = this.value;
            const bar  = document.getElementById('strength-bar');
            const text = document.getElementById('strength-text');
            if (!bar || !text) return;

            let strength = 0;
            if (password.length >= 8)          strength++;
            if (/[A-Z]/.test(password))        strength++;
            if (/[a-z]/.test(password))        strength++;
            if (/[0-9]/.test(password))        strength++;
            if (/[^A-Za-z0-9]/.test(password)) strength++;

            const config = [
                { w: '0%',   c: '#ef4444', t: 'Too Short'   },
                { w: '20%',  c: '#ef4444', t: 'Very Weak'   },
                { w: '40%',  c: '#f97316', t: 'Weak'        },
                { w: '60%',  c: '#eab308', t: 'Good'        },
                { w: '80%',  c: '#2563eb', t: 'Strong'      },
                { w: '100%', c: '#22c55e', t: 'Very Strong' },
            ];

            bar.style.width           = config[strength].w;
            bar.style.backgroundColor = config[strength].c;
            text.innerText            = config[strength].t;
        });
    }

    // -------------------------------------------------------------------------
    // OTP Box Logic
    // -------------------------------------------------------------------------
    const otpInputs = document.querySelectorAll('.otp-box');

    if (otpInputs.length > 0) {
        otpInputs.forEach((input, index) => {
            input.addEventListener('input', e => {
                e.target.value = e.target.value.replace(/[^0-9]/g, '');
                if (e.target.value.length === 1 && index < otpInputs.length - 1) {
                    otpInputs[index + 1].focus();
                }
                updateFinalOTP();
            });

            input.addEventListener('keydown', e => {
                if (e.key === 'Backspace' && input.value === '' && index > 0) {
                    otpInputs[index - 1].focus();
                }
            });
        });
    }

    function updateFinalOTP() {
        let combined     = '';
        const boxes      = document.querySelectorAll('.otp-box');
        const finalInput = document.getElementById('final_otp');

        boxes.forEach(b => (combined += b.value));

        if (finalInput) {
            finalInput.value = combined;
            if (combined.length === 6) {
                document.getElementById('otpForm').submit();
            }
        }
    }

    const otpForm = document.getElementById('otpForm');
    if (otpForm) {
        otpForm.addEventListener('submit', function (e) {
            let combined = '';
            document.querySelectorAll('.otp-box').forEach(b => (combined += b.value));
            document.getElementById('final_otp').value = combined;

            if (combined.length < 6) {
                e.preventDefault();
                showAlert('warning', 'Incomplete Code', 'Please enter the complete 6-digit OTP code.');
            }
        });
    }

});
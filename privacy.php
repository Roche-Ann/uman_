<?php
// privacy.php - Privacy Policy Page
// No session required - public page
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <script>
        (function() {
            const savedTheme = localStorage.getItem('theme') || 'light';
            if (savedTheme === 'dark') {
                document.documentElement.classList.add('dark-theme');
            }
        })();
    </script>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Privacy Policy | LGU Utilities Management System</title>
    <link rel="icon" type="image/png" sizes="32x32" href="assets/images/logocityhall.png">
    <link rel="shortcut icon" type="image/x-icon" href="assets/images/logocityhall.png">

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Public+Sans:wght@300;400;500;600;700&family=Urbanist:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Fira+Code:wght@400;500&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link rel="stylesheet" href="assets/css/responsive.css">

    <style>
        :root {
            --civic-sapphire: #0B3D91;
            --utility-teal: #00A896;
            --insight-amber: #FF9E00;
            --municipal-slate: #1E293B;
            --resident-sand: #F4F1DE;
            --infrastructure-gray: #E0E0E2;
            --progress-emerald: #2A9D8F;
            --alert-coral: #E76F51;

            --font-primary: 'Public Sans', system-ui, -apple-system, sans-serif;
            --font-heading: 'Urbanist', 'Segoe UI', sans-serif;
            --font-mono: 'Fira Code', 'Cascadia Code', monospace;

            --shadow-gentle: 0 8px 32px rgba(11, 61, 145, 0.08);
            --shadow-elevated: 0 16px 48px rgba(11, 61, 145, 0.12);
            --shadow-persistent: 0 4px 24px rgba(11, 61, 145, 0.06);

            --radius-modern: 20px;
            --radius-pill: 9999px;
            --radius-soft: 12px;

            --transition-smooth: all 0.4s cubic-bezier(0.16, 1, 0.3, 1);
            --transition-bounce: all 0.6s cubic-bezier(0.68, -0.55, 0.265, 1.55);
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            -webkit-font-smoothing: antialiased;
            -moz-osx-font-smoothing: grayscale;
        }

        html {
            scroll-behavior: smooth;
            scroll-padding-top: 80px;
        }

        body {
            font-family: var(--font-primary);
            background: linear-gradient(135deg, #FFFFFF 0%, #F8FAFF 50%, #F0F5FF 100%);
            color: var(--municipal-slate);
            line-height: 1.8;
            overflow-x: hidden;
            position: relative;
        }

        body::before {
            content: '';
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background-image: 
                radial-gradient(circle at 10% 20%, rgba(11, 61, 145, 0.03) 0%, transparent 20%),
                radial-gradient(circle at 90% 80%, rgba(0, 168, 150, 0.02) 0%, transparent 20%),
                radial-gradient(circle at 50% 50%, rgba(255, 158, 0, 0.01) 0%, transparent 30%);
            z-index: -1;
            pointer-events: none;
        }

        /* ---------- GLASS NAVIGATION ---------- */
        .civic-navigation {
            position: fixed;
            top: 0;
            width: 100%;
            background: rgba(255, 255, 255, 0.94);
            backdrop-filter: blur(20px);
            -webkit-backdrop-filter: blur(20px);
            border-bottom: 1px solid rgba(11, 61, 145, 0.08);
            z-index: 1000;
            padding: 1rem 0;
            transition: var(--transition-smooth);
        }

        .civic-navigation.scrolled {
            padding: 0.75rem 0;
            box-shadow: var(--shadow-persistent);
        }

        .nav-container {
            max-width: 1280px;
            margin: 0 auto;
            padding: 0 2rem;
            display: flex;
            align-items: center;
            justify-content: space-between;
        }

        .logo-entity {
            display: flex;
            align-items: center;
            gap: 0.75rem;
            text-decoration: none;
        }

        .logo-only {
            width: 58px;
            height: 58px;
            object-fit: contain;
        }

        .logo-text {
            display: flex;
            flex-direction: column;
        }

        .logo-primary {
            font-family: var(--font-heading);
            font-weight: 700;
            font-size: 1.5rem;
            background: linear-gradient(135deg, var(--civic-sapphire), var(--utility-teal));
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
            line-height: 1;
        }

        .logo-secondary {
            font-size: 0.75rem;
            color: var(--municipal-slate);
            opacity: 0.7;
            letter-spacing: 0.5px;
            margin-top: 2px;
        }

        .nav-links {
            display: flex;
            align-items: center;
            gap: 2.5rem;
            list-style: none;
        }

        .nav-link-item {
            position: relative;
        }

        .nav-link {
            font-family: var(--font-heading);
            font-weight: 600;
            color: var(--municipal-slate);
            text-decoration: none;
            font-size: 0.95rem;
            padding: 0.5rem 0;
            position: relative;
            transition: var(--transition-smooth);
        }

        .nav-link::after {
            content: '';
            position: absolute;
            bottom: -2px;
            left: 0;
            width: 0;
            height: 2px;
            background: linear-gradient(90deg, var(--civic-sapphire), var(--utility-teal));
            transition: var(--transition-smooth);
            border-radius: var(--radius-pill);
        }

        .nav-link:hover {
            color: var(--civic-sapphire);
        }

        .nav-link:hover::after {
            width: 100%;
        }

        .nav-actions {
            display: flex;
            align-items: center;
            gap: 1rem;
        }

        /* Buttons */
        .civic-button {
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            padding: 0.75rem 1.75rem;
            border-radius: var(--radius-pill);
            font-family: var(--font-heading);
            font-weight: 600;
            text-decoration: none;
            border: none;
            cursor: pointer;
            transition: var(--transition-smooth);
            position: relative;
            overflow: hidden;
        }

        .civic-button::after {
            content: '';
            position: absolute;
            top: 0;
            left: -100%;
            width: 100%;
            height: 100%;
            background: linear-gradient(90deg, transparent, rgba(255, 255, 255, 0.2), transparent);
            transition: left 0.6s;
        }

        .civic-button:hover::after {
            left: 100%;
        }

        .button-primary {
            background: linear-gradient(135deg, var(--civic-sapphire), var(--utility-teal));
            color: white;
            box-shadow: 0 8px 24px rgba(11, 61, 145, 0.2);
        }

        .button-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 12px 32px rgba(11, 61, 145, 0.3);
        }

        .button-secondary {
            background: white;
            color: var(--civic-sapphire);
            border: 2px solid rgba(11, 61, 145, 0.1);
        }

        .button-secondary:hover {
            border-color: var(--civic-sapphire);
            background: rgba(11, 61, 145, 0.02);
            transform: translateY(-2px);
        }

        .menu-toggle {
            display: none;
            background: none;
            border: none;
            font-size: 1.5rem;
            color: var(--civic-sapphire);
            cursor: pointer;
        }

        /* ---------- CONTENT SECTION ---------- */
        .content-section {
            padding: 140px 2rem 4rem;
            max-width: 1000px;
            margin: 0 auto;
            min-height: 70vh;
        }

        .content-card {
            background: rgba(255, 255, 255, 0.85);
            backdrop-filter: blur(15px);
            border-radius: var(--radius-modern);
            padding: 3rem;
            box-shadow: var(--shadow-elevated);
            border: 1px solid rgba(255, 255, 255, 0.3);
            animation: slideDown 0.7s cubic-bezier(0.34, 1.56, 0.64, 1);
        }

        @keyframes slideDown {
            from { opacity: 0; transform: translateY(-30px); }
            to { opacity: 1; transform: translateY(0); }
        }

        .content-card h1 {
            font-family: var(--font-heading);
            font-size: 2.5rem;
            font-weight: 700;
            color: var(--civic-sapphire);
            margin-bottom: 0.5rem;
        }

        .content-card .last-updated {
            color: var(--municipal-slate);
            opacity: 0.6;
            font-size: 0.9rem;
            margin-bottom: 2rem;
            display: block;
        }

        .content-card h2 {
            font-family: var(--font-heading);
            font-size: 1.5rem;
            font-weight: 700;
            color: var(--civic-sapphire);
            margin-top: 2rem;
            margin-bottom: 1rem;
        }

        .content-card h3 {
            font-family: var(--font-heading);
            font-size: 1.2rem;
            font-weight: 600;
            color: var(--municipal-slate);
            margin-top: 1.5rem;
            margin-bottom: 0.75rem;
        }

        .content-card p, .content-card li {
            color: var(--municipal-slate);
            opacity: 0.85;
            line-height: 1.8;
        }

        .content-card ul, .content-card ol {
            padding-left: 1.5rem;
            margin-bottom: 1rem;
        }

        .content-card li {
            margin-bottom: 0.5rem;
        }

        .content-card .highlight-box {
            background: rgba(11, 61, 145, 0.05);
            border-left: 4px solid var(--civic-sapphire);
            padding: 1.5rem;
            border-radius: var(--radius-soft);
            margin: 1.5rem 0;
        }

        /* ---------- FOOTER ---------- */
        .civic-footer {
            background: var(--municipal-slate);
            color: white;
            padding: 4rem 0 2rem;
            margin-top: 0;
        }

        .footer-grid {
            max-width: 1280px;
            margin: 0 auto;
            padding: 0 2rem;
            display: grid;
            grid-template-columns: repeat(5, 1fr);
            gap: 2rem;
            margin-bottom: 3rem;
        }

        .footer-brand .footer-logo {
            display: flex;
            align-items: center;
            gap: 0.75rem;
            margin-bottom: 1rem;
        }

        .footer-logo-icon {
            width: 40px;
            height: 40px;
            background: white;
            border-radius: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: var(--civic-sapphire);
            font-size: 1.25rem;
        }

        .footer-logo-text {
            font-family: var(--font-heading);
            font-size: 1.25rem;
            font-weight: 700;
            color: white;
        }

        .footer-description {
            opacity: 0.8;
            font-size: 0.9rem;
            line-height: 1.6;
        }

        .footer-heading {
            font-family: var(--font-heading);
            font-size: 1.125rem;
            font-weight: 700;
            margin-bottom: 1.5rem;
            color: white;
        }

        .footer-links {
            list-style: none;
        }

        .footer-links li {
            margin-bottom: 0.75rem;
        }

        .footer-links a {
            color: rgba(255, 255, 255, 0.8);
            text-decoration: none;
            transition: var(--transition-smooth);
            font-size: 0.9rem;
        }

        .footer-links a:hover {
            color: white;
            padding-left: 4px;
        }

        .footer-bottom {
            max-width: 1280px;
            margin: 0 auto;
            padding: 0 2rem;
            text-align: center;
            border-top: 1px solid rgba(255, 255, 255, 0.1);
            padding-top: 2rem;
        }

        .footer-copyright {
            opacity: 0.7;
            font-size: 0.875rem;
        }

        @media (max-width: 992px) {
            .menu-toggle {
                display: block;
            }
            .nav-actions {
                display: none;
            }
            .nav-links {
                position: fixed;
                top: 80px;
                right: -100%;
                width: 80%;
                height: calc(100vh - 80px);
                background: white;
                flex-direction: column;
                justify-content: flex-start;
                padding: 2rem;
                box-shadow: -10px 0 30px rgba(0,0,0,0.1);
                transition: 0.4s ease-in-out;
                z-index: 1000;
            }
            .nav-links.active {
                right: 0;
            }
            .nav-links .mobile-nav-actions {
                display: flex;
                flex-direction: column;
                gap: 1rem;
                width: 100%;
                margin-top: 2rem;
            }
            .footer-grid {
                grid-template-columns: repeat(2, 1fr);
            }
        }

        @media (max-width: 768px) {
            .nav-container {
                padding: 0 1rem;
            }
            .content-card {
                padding: 1.5rem;
            }
            .content-card h1 {
                font-size: 1.8rem;
            }
            .footer-grid {
                grid-template-columns: 1fr;
                gap: 2rem;
            }
        }
    </style>
</head>
<body>

    <!-- Navigation -->
    <nav class="civic-navigation" id="mainNav">
        <div class="nav-container">
            <a href="home.php" class="logo-entity">
                <img src="assets/images/logocityhall.png" alt="LGU Logo" class="logo-only">
                <div class="logo-text">
                    <span class="logo-primary">Utilities Management System</span>
                    <span class="logo-secondary"><span class="title-gradient">uMAN</span></span>
                </div>
            </a>

            <button class="menu-toggle" id="mobileMenuBtn">
                <i class="fas fa-bars"></i>
            </button>

            <ul class="nav-links" id="navLinksList">
                <li class="nav-link-item"><a href="home.php" class="nav-link">Home</a></li>
                <li class="nav-link-item"><a href="home.php#modules" class="nav-link">Modules</a></li>
                <li class="nav-link-item"><a href="home.php#about" class="nav-link">About</a></li>
                <li class="nav-link-item"><a href="home.php#contacts" class="nav-link">Contact</a></li>
                <li class="mobile-nav-actions" style="display: none;">
                    <a href="create.php" class="civic-button button-secondary" style="width:100%; justify-content:center;">Register</a>
                    <a href="login.php" class="civic-button button-primary" style="width:100%; justify-content:center;">Login</a>
                </li>
            </ul>

            <div class="nav-actions">
                <a href="create.php" class="civic-button button-secondary">Register</a>
                <a href="login.php" class="civic-button button-primary">Login</a>
            </div>
        </div>
    </nav>

    <!-- Content Section -->
    <section class="content-section">
        <div class="content-card">
            <h1>Privacy Policy</h1>
            <span class="last-updated">Last Updated: January 1, 2026</span>

            <p>
                The Local Government Unit (LGU) is committed to protecting your privacy and ensuring the security of your personal data. This Privacy Policy explains how we collect, use, and safeguard your information when you use the LGU Utilities Management System.
            </p>

            <div class="highlight-box">
                <strong>📌 Key Principle:</strong> We collect only the data necessary to provide and improve our services, and we never sell or share your personal information with third parties without your explicit consent, except as required by law.
            </div>

            <h2>1. Information We Collect</h2>
            <p>When you register and use our system, we collect the following types of information:</p>
            <ul>
                <li><strong>Personal Information:</strong> Full name, email address, contact number, and residential address.</li>
                <li><strong>Account Credentials:</strong> Securely hashed password for account authentication.</li>
                <li><strong>Usage Data:</strong> Service requests, incident reports, payment history, and utility consumption records.</li>
                <li><strong>Device Information:</strong> IP address, browser type, and access times for security and analytics purposes.</li>
                <li><strong>Location Data:</strong> GPS coordinates (if provided) for asset mapping and incident reporting.</li>
            </ul>

            <h2>2. How We Use Your Information</h2>
            <p>Your personal data is used exclusively for the following purposes:</p>
            <ul>
                <li><strong>Service Delivery:</strong> Processing service requests, incident reports, and maintenance coordination.</li>
                <li><strong>Account Management:</strong> Managing your user account, authentication, and access control.</li>
                <li><strong>Communication:</strong> Sending OTP verification codes, service updates, and advisory notifications.</li>
                <li><strong>Analytics:</strong> Generating reports on utility consumption, incident trends, and system performance.</li>
                <li><strong>Legal Compliance:</strong> Fulfilling regulatory requirements and responding to lawful requests from government authorities.</li>
            </ul>

            <h2>3. Data Sharing and Disclosure</h2>
            <p>We do not sell, trade, or rent your personal information to third parties. Your data may be shared only in the following circumstances:</p>
            <ul>
                <li><strong>Service Providers:</strong> With trusted third-party service providers (e.g., email delivery services) who assist in system operations.</li>
                <li><strong>Legal Requirements:</strong> When required by law, court order, or government regulation.</li>
                <li><strong>Public Safety:</strong> To protect the rights, property, or safety of the LGU, residents, or the public.</li>
            </ul>

            <h2>4. Data Security</h2>
            <p>
                We implement industry-standard security measures to protect your data, including:
            </p>
            <ul>
                <li><strong>Encryption:</strong> All data transmitted between your browser and our servers is encrypted using SSL/TLS.</li>
                <li><strong>Secure Storage:</strong> Passwords are hashed using bcrypt, and sensitive data is stored in encrypted databases.</li>
                <li><strong>Access Control:</strong> Only authorized LGU personnel have access to user data, and all access is logged.</li>
                <li><strong>Regular Audits:</strong> We conduct periodic security audits to identify and address vulnerabilities.</li>
            </ul>

            <h2>5. Your Rights Under the Data Privacy Act (RA 10173)</h2>
            <p>As a data subject, you have the following rights:</p>
            <ul>
                <li><strong>Right to Access:</strong> Request a copy of the personal data we hold about you.</li>
                <li><strong>Right to Rectification:</strong> Request correction of inaccurate or incomplete data.</li>
                <li><strong>Right to Erasure:</strong> Request deletion of your personal data (subject to legal retention requirements).</li>
                <li><strong>Right to Object:</strong> Object to the processing of your data for specific purposes.</li>
                <li><strong>Right to Portability:</strong> Request transfer of your data to another service provider.</li>
            </ul>
            <p>To exercise these rights, please contact our Data Protection Officer (DPO) at the email address provided below.</p>

            <h2>6. Data Retention</h2>
            <p>
                We retain your personal data only as long as necessary to fulfill the purposes outlined in this policy, or as required by law. Service request records, incident reports, and transaction history are retained for a minimum of <strong>five (5) years</strong> for audit and legal compliance purposes.
            </p>

            <h2>7. Cookies and Tracking Technologies</h2>
            <p>
                Our system uses session cookies to maintain your login state and improve user experience. These cookies are temporary and are deleted when you close your browser. We do not use third-party tracking cookies for advertising purposes.
            </p>

            <h2>8. Changes to This Privacy Policy</h2>
            <p>
                We reserve the right to update this Privacy Policy to reflect changes in our practices or legal requirements. We will notify users of significant changes through the system dashboard or via email. The "Last Updated" date at the top of this policy will indicate when changes were made.
            </p>

            <h2>9. Contact Information</h2>
            <p>
                If you have questions, concerns, or requests regarding this Privacy Policy or your personal data, please contact us:
            </p>
            <ul>
                <li><strong>Data Protection Officer:</strong> LGU Data Privacy Office</li>
                <li><strong>Email:</strong> <a href="mailto:dpo@lgu.gov.ph">dpo@lgu.gov.ph</a></li>
                <li><strong>Phone:</strong> <a href="tel:+63212345678">+63 2 1234 5678</a></li>
                <li><strong>Address:</strong> City Hall, Quezon City, Philippines</li>
            </ul>

            <p style="margin-top: 2rem; color: var(--civic-sapphire); font-weight: 600;">
                By using the LGU Utilities Management System, you consent to the collection and use of your information as described in this Privacy Policy.
            </p>
        </div>
    </section>

    <!-- Footer -->
    <footer class="civic-footer">
        <div class="footer-grid">
            <div class="footer-brand">
                <div class="footer-logo">
                    <div class="footer-logo-icon">
                        <i class="fas fa-bolt"></i>
                    </div>
                    <span class="footer-logo-text">Quezon City<br><small>Web-Based Utilities Management</small></span>
                </div>
                <p class="footer-description">
                    UMAN is a utility management platform that helps local government units manage assets, incidents, maintenance, and energy and water monitoring in one system.
                </p>
            </div>
            <div>
                <h4 class="footer-heading">Quick Links</h4>
                <ul class="footer-links">
                    <li><a href="home.php">Home</a></li>
                    <li><a href="home.php#modules">Modules</a></li>
                    <li><a href="home.php#about">About</a></li>
                    <li><a href="home.php#contacts">Contact</a></li>
                </ul>
            </div>
            <div>
                <h4 class="footer-heading">Services</h4>
                <ul class="footer-links">
                    <li><a href="home.php#modules">Asset Inventory</a></li>
                    <li><a href="home.php#modules">Incident Reporting</a></li>
                    <li><a href="home.php#modules">Maintenance Dispatch</a></li>
                    <li><a href="home.php#modules">Energy Monitoring</a></li>
                </ul>
            </div>
            <div>
                <h4 class="footer-heading">Contact</h4>
                <ul class="footer-links">
                    <li>Email: <a href="mailto:info@lgu.gov.ph">info@lgu.gov.ph</a></li>
                    <li>Phone: <a href="tel:+63212345678">+63 2 1234 5678</a></li>
                    <li>Address: City Hall, Quezon City</li>
                    <li><a href="https://www.google.com/maps/place/Quezon+City+Hall" target="_blank"><i class="fas fa-map-marker-alt"></i> Location</a></li>
                </ul>
            </div>
            <div>
                <h4 class="footer-heading">Legal & Compliance</h4>
                <ul class="footer-links">
                    <li><a href="privacy.php">Privacy Policy</a></li>
                    <li><a href="terms.php">Terms & Conditions</a></li>
                    <li><a href="dataprivacy.php">Data Privacy Notice</a></li>
                </ul>
            </div>
        </div>
        <div class="footer-bottom">
            <div class="footer-copyright">
                &copy; 2026 LGU · All Rights Reserved
            </div>
        </div>
    </footer>

    <script>
        // Mobile menu toggle
        const mobileMenuBtn = document.getElementById('mobileMenuBtn');
        const navLinksList = document.getElementById('navLinksList');
        const mobileActionsGroup = document.querySelector('.mobile-nav-actions');

        if (mobileMenuBtn && navLinksList) {
            mobileMenuBtn.addEventListener('click', () => {
                navLinksList.classList.toggle('active');
                const icon = mobileMenuBtn.querySelector('i');
                if (navLinksList.classList.contains('active')) {
                    icon.className = 'fas fa-times';
                    if (window.innerWidth <= 992 && mobileActionsGroup) {
                        mobileActionsGroup.style.display = 'flex';
                    }
                } else {
                    icon.className = 'fas fa-bars';
                    if (mobileActionsGroup) mobileActionsGroup.style.display = 'none';
                }
            });

            document.querySelectorAll('.nav-link').forEach(link => {
                link.addEventListener('click', () => {
                    navLinksList.classList.remove('active');
                    if (mobileMenuBtn) {
                        const icon = mobileMenuBtn.querySelector('i');
                        if (icon) icon.className = 'fas fa-bars';
                    }
                    if (mobileActionsGroup) mobileActionsGroup.style.display = 'none';
                });
            });

            window.addEventListener('resize', () => {
                if (window.innerWidth > 992) {
                    navLinksList.classList.remove('active');
                    if (mobileMenuBtn) {
                        const icon = mobileMenuBtn.querySelector('i');
                        if (icon) icon.className = 'fas fa-bars';
                    }
                    if (mobileActionsGroup) mobileActionsGroup.style.display = 'none';
                }
            });
        }

        // Add scrolled class to navigation on scroll
        const nav = document.getElementById('mainNav');
        if (nav) {
            window.addEventListener('scroll', () => {
                if (window.scrollY > 10) {
                    nav.classList.add('scrolled');
                } else {
                    nav.classList.remove('scrolled');
                }
            });
        }
    </script>
</body>
</html>
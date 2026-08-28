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
    <title>Home | Utilities Management System</title>

    <link rel="icon" type="image/png" sizes="32x32" href="assets/images/logocityhall.png">
    <link rel="shortcut icon" type="image/x-icon" href="assets/images/logocityhall.png">

    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://cdn.jsdelivr.net/npm/@shoelace-style/shoelace@2.0.0-beta.83/dist/themes/light.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/luminous-lightbox@2.3.5/dist/luminous-basic.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/choices.js/public/assets/styles/choices.min.css">
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
        
        .logo-marker {
            background: none !important; 
            border-radius: 0 !important;  
        }

        .logo-marker::before {
            content: none !important; 
        }

        .logo-only {
            width: 58px;
            height: 58px;
            object-fit: contain;
        }

        .logo-icon {
            font-size: 1.5rem;
            color: white;
            position: relative;
            z-index: 1;
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
        
        .civic-button::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: inherit;
            z-index: -1;
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
        
        .hero-platform {
            padding: 160px 0 100px;
            position: relative;
            overflow: hidden;
        }
        
        .hero-grid {
            max-width: 1280px;
            margin: 0 auto;
            padding: 0 2rem;
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 4rem;
            align-items: center;
        }
        
        .hero-content {
            position: relative;
        }
        
        .context-badge {
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            background: linear-gradient(135deg, rgba(11, 61, 145, 0.1), rgba(0, 168, 150, 0.1));
            color: var(--civic-sapphire);
            padding: 0.5rem 1rem;
            border-radius: var(--radius-pill);
            font-size: 0.875rem;
            font-weight: 600;
            margin-bottom: 1.5rem;
            border: 1px solid rgba(11, 61, 145, 0.1);
        }
        
        .hero-title {
            font-family: var(--font-heading);
            font-size: 3.5rem;
            font-weight: 800;
            line-height: 1.1;
            color: var(--municipal-slate);
            margin-bottom: 1.5rem;
        }
        
        .title-gradient {
            background: linear-gradient(135deg, var(--civic-sapphire), var(--utility-teal), var(--insight-amber));
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
        }
        
        .hero-description {
            font-size: 1.125rem;
            color: rgba(47, 72, 88, 0.8);
            margin-bottom: 2.5rem;
            max-width: 540px;
        }
        
        .hero-visual {
            position: relative;
        }
        
        .visual-container {
            position: relative;
            border-radius: var(--radius-modern);
            overflow: hidden;
            box-shadow: var(--shadow-elevated);
            transform: perspective(1000px) rotateY(-5deg) rotateX(2deg);
            transition: var(--transition-bounce);
        }
        
        .visual-container:hover {
            transform: perspective(1000px) rotateY(0deg) rotateX(0deg);
        }
        
        .visual-overlay {
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: linear-gradient(45deg, rgba(11, 61, 145, 0.05), transparent 30%);
            pointer-events: none;
        }
        
        .stat-panel {
            position: absolute;
            bottom: 2rem;
            left: 2rem;
            right: 2rem;
            background: rgba(255, 255, 255, 0.92);
            backdrop-filter: blur(20px);
            -webkit-backdrop-filter: blur(20px);
            border-radius: var(--radius-soft);
            padding: 1.5rem;
            box-shadow: var(--shadow-gentle);
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 1rem;
        }
        
        .stat-item {
            text-align: center;
        }
        
        .stat-value {
            font-family: var(--font-heading);
            font-size: 2rem;
            font-weight: 800;
            background: linear-gradient(135deg, var(--civic-sapphire), var(--utility-teal));
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
            line-height: 1;
        }
        
        .stat-label {
            font-size: 0.875rem;
            color: rgba(47, 72, 88, 0.7);
            margin-top: 0.25rem;
        }
        
        .modules-section {
            padding: 6rem 0;
            position: relative;
        }
        
        .section-header {
            text-align: center;
            max-width: 800px;
            margin: 0 auto 4rem;
        }
        
        .section-preface {
            font-size: 0.875rem;
            text-transform: uppercase;
            letter-spacing: 2px;
            color: var(--utility-teal);
            font-weight: 600;
            margin-bottom: 1rem;
        }
        
        .section-title {
            font-family: var(--font-heading);
            font-size: 2.5rem;
            font-weight: 700;
            color: var(--municipal-slate);
            margin-bottom: 1rem;
        }
        
        .section-description {
            font-size: 1.125rem;
            color: rgba(47, 72, 88, 0.8);
            line-height: 1.7;
        }
        
        .modules-grid {
            max-width: 1280px;
            margin: 0 auto;
            padding: 0 2rem;
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(350px, 1fr));
            gap: 2rem;
        }
        
        .module-card {
            background: white;
            border-radius: var(--radius-modern);
            padding: 2.5rem;
            box-shadow: var(--shadow-gentle);
            border: 1px solid rgba(11, 61, 145, 0.08);
            transition: var(--transition-smooth);
            position: relative;
            overflow: hidden;
        }
        
        .module-card:hover {
            transform: translateY(-8px);
            box-shadow: var(--shadow-elevated);
            border-color: transparent;
        }
        
        .module-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 4px;
            background: linear-gradient(90deg, var(--civic-sapphire), var(--utility-teal));
        }
        
        .module-header {
            display: flex;
            align-items: center;
            gap: 1rem;
            margin-bottom: 1.5rem;
        }
        
        .module-icon {
            width: 56px;
            height: 56px;
            border-radius: 16px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.5rem;
            flex-shrink: 0;
        }
        
        .icon-reading { background: linear-gradient(135deg, #3B82F6, #60A5FA); color: white; }
        .icon-billing { background: linear-gradient(135deg, #10B981, #34D399); color: white; }
        .icon-payment { background: linear-gradient(135deg, #F59E0B, #FBBF24); color: white; }
        .icon-service { background: linear-gradient(135deg, #8B5CF6, #A78BFA); color: white; }
        .icon-complaint { background: linear-gradient(135deg, #EF4444, #F87171); color: white; }
        .icon-planning { background: linear-gradient(135deg, #14B8A6, #2DD4BF); color: white; }
        
        .module-title {
            font-family: var(--font-heading);
            font-size: 1.5rem;
            font-weight: 700;
            color: var(--municipal-slate);
        }
        
        .module-description {
            color: rgba(47, 72, 88, 0.8);
            margin-bottom: 1.5rem;
            line-height: 1.7;
        }
        
        .module-features {
            list-style: none;
        }
        
        .module-features li {
            display: flex;
            align-items: center;
            gap: 0.75rem;
            margin-bottom: 0.75rem;
            color: rgba(47, 72, 88, 0.8);
            font-size: 0.95rem;
        }
        
        .module-features li::before {
            content: '✓';
            color: var(--progress-emerald);
            font-weight: 700;
            flex-shrink: 0;
        }
        .methodology-section {
            padding: 6rem 0;
            background: linear-gradient(135deg, #F8FAFF 0%, #F0F5FF 100%);
            position: relative;
        }
        
        .methodology-container {
            max-width: 1280px;
            margin: 0 auto;
            padding: 0 2rem;
        }
        
        .methodology-stack {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 2rem;
            margin-top: 3rem;
        }
        
        .method-card {
            background: white;
            border-radius: var(--radius-modern);
            padding: 2.5rem;
            box-shadow: var(--shadow-gentle);
            transition: var(--transition-smooth);
            border-left: 4px solid var(--civic-sapphire);
        }
        
        .method-card:hover {
            transform: translateY(-4px);
            box-shadow: var(--shadow-elevated);
        }
        
        .method-icon {
            width: 64px;
            height: 64px;
            border-radius: 20px;
            background: linear-gradient(135deg, var(--civic-sapphire), var(--utility-teal));
            display: flex;
            align-items: center;
            justify-content: center;
            margin-bottom: 1.5rem;
            font-size: 1.75rem;
            color: white;
        }
        
        .method-title {
            font-family: var(--font-heading);
            font-size: 1.5rem;
            font-weight: 700;
            color: var(--municipal-slate);
            margin-bottom: 1rem;
        }
        
        .method-description {
            color: rgba(47, 72, 88, 0.8);
            line-height: 1.7;
        }
        
        .analytics-showcase {
            padding: 6rem 0;
            position: relative;
        }
        
        .analytics-grid {
            max-width: 1280px;
            margin: 0 auto;
            padding: 0 2rem;
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 4rem;
            align-items: center;
        }
        
        .analytics-visual {
            position: relative;
        }
        
        .ai-visual {
            position: relative;
            border-radius: var(--radius-modern);
            overflow: hidden;
            background: linear-gradient(135deg, var(--municipal-slate), #1E293B);
            padding: 2rem;
            box-shadow: var(--shadow-elevated);
            min-height: 300px;
        }
        
        .ai-insights {
            display: flex;
            flex-direction: column;
            gap: 1.5rem;
        }
        
        .insight-item {
            background: white;
            border-radius: var(--radius-soft);
            padding: 1.5rem;
            border-left: 4px solid var(--civic-sapphire);
            box-shadow: var(--shadow-gentle);
            transition: var(--transition-smooth);
        }
        
        .insight-item:hover {
            transform: translateX(8px);
        }
        
        .insight-header {
            display: flex;
            align-items: center;
            gap: 0.75rem;
            margin-bottom: 0.75rem;
        }
        
        .insight-icon {
            width: 32px;
            height: 32px;
            border-radius: 8px;
            background: rgba(11, 61, 145, 0.1);
            display: flex;
            align-items: center;
            justify-content: center;
            color: var(--civic-sapphire);
            flex-shrink: 0;
        }
        
        .insight-title {
            font-family: var(--font-heading);
            font-size: 1.125rem;
            font-weight: 700;
            color: var(--municipal-slate);
        }
        
        .insight-description {
            color: rgba(47, 72, 88, 0.8);
            font-size: 0.95rem;
        }
        .cta-section {
            padding: 6rem 0;
            position: relative;
            background: linear-gradient(135deg, var(--civic-sapphire), var(--utility-teal));
            color: white;
            overflow: hidden;
        }
        
        .cta-container {
            max-width: 800px;
            margin: 0 auto;
            padding: 0 2rem;
            text-align: center;
            position: relative;
            z-index: 1;
        }
        
        .cta-title {
            font-family: var(--font-heading);
            font-size: 2.5rem;
            font-weight: 700;
            margin-bottom: 1.5rem;
        }
        
        .cta-description {
            font-size: 1.125rem;
            opacity: 0.9;
            margin-bottom: 3rem;
            max-width: 600px;
            margin-left: auto;
            margin-right: auto;
        }
        
        .cta-actions {
            display: flex;
            gap: 1rem;
            justify-content: center;
            flex-wrap: wrap;
        }
        
        .cta-button-light {
            background: white;
            color: var(--civic-sapphire);
        }
        
        .cta-button-outline {
            background: transparent;
            color: white;
            border: 2px solid rgba(255, 255, 255, 0.3);
        }
        
        .cta-button-outline:hover {
            background: rgba(255, 255, 255, 0.1);
            border-color: white;
        }
        
        .civic-footer {
            background: var(--municipal-slate);
            color: white;
            padding: 4rem 0 2rem;
            position: relative;
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
        
        .footer-brand {
            display: flex;
            flex-direction: column;
            gap: 1.5rem;
        }
        
        .footer-logo {
            display: flex;
            align-items: center;
            gap: 0.75rem;
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
            line-height: 1.7;
            font-size: 0.95rem;
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
            font-size: 0.95rem;
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
        
        @media (max-width: 1024px) {
            .hero-grid,
            .analytics-grid {
                grid-template-columns: 1fr;
                gap: 3rem;
            }
            
            .hero-title {
                font-size: 2.75rem;
            }
            
            .section-title {
                font-size: 2rem;
            }
        }
        
        @media (max-width: 992px) {
            .footer-grid {
                grid-template-columns: repeat(2, 1fr);
            }
        }
        
        @media (max-width: 768px) {
            /* ── Global page resets for mobile ── */
            html, body {
                overflow-x: hidden !important;
                max-width: 100vw;
            }

            body {
                background: #ffffff !important;
            }

            /* ── Navigation ── */
            .nav-container {
                padding: 0 1rem;
            }
            
            .nav-links {
                display: none;
            }

            /* ── Hero Section ── */
            .hero-platform {
                padding: 100px 0 60px;
                background: linear-gradient(160deg, #0B3D91 0%, #1a5276 40%, #00A896 100%);
            }

            .hero-grid {
                grid-template-columns: 1fr;
                gap: 2rem;
                padding: 0 1.25rem;
                text-align: center;
            }

            .hero-content {
                display: flex;
                flex-direction: column;
                align-items: center;
            }

            .context-badge {
                margin-left: auto;
                margin-right: auto;
                background: rgba(255,255,255,0.15);
                color: #ffffff;
                border-color: rgba(255,255,255,0.25);
            }

            .hero-title {
                font-size: 2rem;
                color: #ffffff;
                text-align: center;
            }

            .title-gradient {
                background: linear-gradient(135deg, #7ec8e3, #FFD700, #00e5c9);
                -webkit-background-clip: text;
                -webkit-text-fill-color: transparent;
                background-clip: text;
            }

            .hero-description {
                font-size: 1rem;
                color: rgba(255,255,255,0.85);
                text-align: center;
                max-width: 100%;
            }

            .hero-platform .cta-actions {
                flex-direction: column;
                align-items: center;
                width: 100%;
                gap: 0.75rem;
            }

            .hero-platform .cta-actions .civic-button {
                width: 100%;
                max-width: 320px;
                justify-content: center;
            }

            .hero-visual {
                width: 100%;
            }

            .visual-container {
                transform: none !important;
                border-radius: 16px;
            }

            /* ── Sections general ── */
            .modules-section,
            .methodology-section,
            .analytics-showcase,
            .cta-section {
                padding: 3rem 0;
            }

            .section-header {
                padding: 0 1.25rem;
                margin-bottom: 2rem;
            }

            .section-title {
                font-size: 1.6rem;
            }

            .section-description {
                font-size: 0.95rem;
            }

            /* ── Modules ── */
            .modules-grid {
                grid-template-columns: 1fr;
                padding: 0 1.25rem;
            }

            /* ── History section ── */
            .methodology-section {
                background: #F8FAFF !important;
            }

            .methodology-container {
                padding: 0 1.25rem;
            }

            .methodology-stack {
                grid-template-columns: 1fr;
                gap: 1.25rem;
            }

            /* ── Stat panel ── */
            .stat-panel {
                position: relative;
                bottom: auto;
                left: auto;
                right: auto;
                margin-top: 1.5rem;
            }

            /* ── Analytics / Contact section ── */
            .analytics-showcase {
                background: #ffffff !important;
            }

            .analytics-grid {
                grid-template-columns: 1fr;
                gap: 2rem;
                padding: 0 1.25rem;
            }

            .ai-visual {
                background: transparent !important;
                padding: 0 !important;
                box-shadow: none !important;
                min-height: 260px;
            }

            /* ── CTA section ── */
            .cta-actions {
                flex-direction: column;
                align-items: center;
            }
            
            .cta-button {
                width: 100%;
                justify-content: center;
            }

            .cta-title {
                font-size: 1.75rem;
            }

            .cta-description {
                font-size: 1rem;
            }
            
            /* ── Footer ── */
            .footer-grid {
                grid-template-columns: 1fr;
                gap: 2rem;
                padding: 0 1.25rem;
            }

            .footer-bottom {
                padding: 0 1.25rem;
                padding-top: 2rem;
            }
        }
        
        .hover-lift {
            transition: var(--transition-smooth);
        }
        
        .hover-lift:hover {
            transform: translateY(-4px);
        }
        
        :focus-visible {
            outline: 2px solid var(--civic-sapphire);
            outline-offset: 2px;
        }

        /* ── Mobile Carousel for Featured Services ── */
        .modules-carousel-wrapper {
            display: none; /* hidden on desktop */
        }

        @media (max-width: 768px) {
            /* Navbar compact fix */
            .civic-navigation {
                padding: 0.6rem 0;
            }
            .logo-only {
                width: 38px;
                height: 38px;
            }
            .logo-primary {
                font-size: 1rem;
                line-height: 1.15;
            }
            .logo-secondary {
                font-size: 0.65rem;
            }
            .nav-actions .civic-button {
                padding: 0.5rem 0.9rem;
                font-size: 0.8rem;
                gap: 0.25rem;
            }
            .nav-actions {
                gap: 0.5rem;
            }

            /* Fix dark background on modules & other sections */
            .modules-section {
                background: #ffffff !important;
            }

            /* Hide desktop grid, show carousel */
            .modules-grid {
                display: none !important;
            }
            .modules-carousel-wrapper {
                display: block;
                padding: 0 1.25rem;
                position: relative;
                height: 400px; /* fixed height for stack */
                margin-top: 1rem;
                overflow: visible !important;
            }
            .modules-carousel-track {
                position: relative;
                width: 100%;
                height: 100%;
                overflow: visible !important;
            }
            .modules-carousel-track .module-card {
                position: absolute !important;
                top: 0;
                left: 0;
                width: 100% !important;
                height: 100% !important;
                margin: 0 !important;
                box-sizing: border-box;
                background: #ffffff;
                border-radius: var(--radius-modern);
                padding: 2.2rem 1.8rem;
                box-shadow: 0 10px 30px rgba(0, 0, 0, 0.08) !important;
                border: 1px solid rgba(11, 61, 145, 0.08);
                transform-origin: center bottom;
                transition: transform 0.45s cubic-bezier(0.16, 1, 0.3, 1),
                            opacity 0.45s cubic-bezier(0.16, 1, 0.3, 1),
                            z-index 0.45s ease;
                opacity: 0;
                pointer-events: none;
                z-index: 0;
                transform: translate3d(0, 20px, 0) scale(0.9);
            }

            .dark-theme .modules-carousel-track .module-card {
                background: #ffffff !important;
                border-color: rgba(11, 61, 145, 0.08) !important;
                box-shadow: 0 10px 30px rgba(0, 0, 0, 0.08) !important;
            }

            /* Front/active card */
            .modules-carousel-track .module-card.card-active {
                opacity: 1;
                pointer-events: auto;
                z-index: 3;
                transform: translate3d(0, 0, 0) scale(1);
                will-change: transform, opacity;
            }

            /* Stacked card behind front card */
            .modules-carousel-track .module-card.card-next {
                opacity: 0.7;
                pointer-events: none;
                z-index: 2;
                transform: translate3d(0, 16px, 0) scale(0.94);
                will-change: transform, opacity;
            }

            /* Fly-out animation classes */
            .modules-carousel-track .module-card.card-swiped-left {
                opacity: 0;
                pointer-events: none;
                z-index: 4;
                transform: translate3d(-130%, 0, 0) rotate(-12deg) !important;
                will-change: transform, opacity;
            }

            .modules-carousel-track .module-card.card-swiped-right {
                opacity: 0;
                pointer-events: none;
                z-index: 4;
                transform: translate3d(130%, 0, 0) rotate(12deg) !important;
                will-change: transform, opacity;
            }

            /* ── Dark Theme Card Content Contrast Fix (Keep them light cards with dark text) ── */
            .dark-theme .modules-carousel-track .module-card .module-title {
                color: var(--municipal-slate) !important;
            }
            .dark-theme .modules-carousel-track .module-card .module-description {
                color: rgba(47, 72, 88, 0.8) !important;
            }
            .dark-theme .modules-carousel-track .module-card .module-features li {
                color: rgba(47, 72, 88, 0.8) !important;
            }

            /* ── Dark Theme Contact cards styling (Keep them light cards with dark text) ── */
            .dark-theme .insight-item {
                background: #ffffff !important;
                border-left-color: var(--civic-sapphire) !important;
                box-shadow: 0 8px 30px rgba(0,0,0,0.08) !important;
            }
            .dark-theme .insight-title {
                color: var(--municipal-slate) !important;
            }
            .dark-theme .insight-description {
                color: rgba(47, 72, 88, 0.8) !important;
            }
            .dark-theme .insight-description a {
                color: var(--civic-sapphire) !important;
            }
            .dark-theme .insight-icon {
                background: rgba(11, 61, 145, 0.1) !important;
                color: var(--civic-sapphire) !important;
            }

            /* Dot indicators */
            .carousel-dots {
                display: flex;
                justify-content: center;
                gap: 6px;
                margin-top: 32px;
                position: relative;
                z-index: 10;
            }
            .carousel-dot {
                width: 8px;
                height: 8px;
                border-radius: 50%;
                background: #cbd5e1;
                transition: all 0.3s ease;
                cursor: pointer;
                border: none;
                padding: 0;
            }
            .carousel-dot.active {
                background: var(--civic-sapphire);
                width: 22px;
                border-radius: 99px;
            }

            /* ── Footer mobile space optimization ── */
            .civic-footer {
                padding: 1.5rem 0 1rem !important;
            }
            .footer-grid {
                grid-template-columns: 1fr !important;
                gap: 1rem !important;
            }
            /* Place link groups side-by-side on mobile to save vertical space */
            .footer-grid > div:not(.footer-brand) {
                display: inline-block !important;
                width: 46% !important;
                margin-right: 3% !important;
                vertical-align: top !important;
                margin-bottom: 0.75rem !important;
                box-sizing: border-box !important;
            }
            .footer-grid > div:last-child {
                width: 95% !important;
                margin-right: 0 !important;
            }
            .footer-brand {
                margin-bottom: 0.25rem !important;
            }
            .footer-description {
                font-size: 0.8rem !important;
                line-height: 1.4 !important;
            }
            .footer-heading {
                font-size: 0.85rem !important;
                margin-bottom: 0.4rem !important;
            }
            .footer-links li {
                margin-bottom: 0.25rem !important;
                line-height: 1.3 !important;
            }
            .footer-links a {
                font-size: 0.8rem !important;
            }
            .footer-bottom {
                padding: 0 1.25rem;
                padding-top: 1rem !important;
                margin-top: 1rem !important;
            }
        }
    </style>
    
    <!-- Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Public+Sans:wght@300;400;500;600;700&family=Urbanist:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Fira+Code:wght@400;500&display=swap" rel="stylesheet">
</head>
<body>
    <!-- Civic Navigation -->
    <nav class="civic-navigation" id="mainNav">
        <div class="nav-container">
            <a href="#" class="logo-entity">
                <div class="logo-marker">
                    <img src="assets/images/logocityhall.png" alt="LGU Logo" class="logo-only">
                </div>
                <div class="logo-text">
                    <span class="logo-primary">Utilities Management System</span>
                    <span class="logo-secondary"><span class="title-gradient">uMAN</span> · LGU Command Center</span>
                </div>
            </a>
            
            <ul class="nav-links">
                <li class="nav-link-item"><a href="#hero" class="nav-link">Home</a></li>
                <li class="nav-link-item"><a href="#modules" class="nav-link">Modules</a></li>
                <li class="nav-link-item"><a href="#about" class="nav-link">About</a></li>
                <li class="nav-link-item"><a href="#contacts" class="nav-link">Contact</a></li>
            </ul>
            
            <div class="nav-actions">
                <a href="create.php" class="civic-button button-secondary">Register</a>
                <a href="login.php" class="civic-button button-primary">Login</a>
            </div>
        </div>
    </nav>

    <!-- Hero Platform Section -->
    <section class="hero-platform" id="hero">
        <div class="hero-grid">
            <div class="hero-content">
                <div class="context-badge">
                    <i class="fas fa-university"></i>
                    <span>Local Government Unit</span>
                </div>
                
                <h1 class="hero-title">
                    A Web-based Utilities Management System<br>
                    <span class="title-gradient">with resident portal and AI Analytics</span>
                </h1>
                
                <p class="hero-description">
                    We Streamline utility management with easy access to utility operations with real-time monitoring, automated workflows, and AI-powered insights.
                </p>
                
                <div class="cta-actions">
                    <a href="#modules" class="civic-button button-primary hover-lift">
                        <i class="fas fa-portal-entrance"></i>
                        Explore Modules
                    </a>
                    <a href="login.php" class="civic-button button-secondary hover-lift">
                        <i class="fas fa-layer-group"></i>
                        Get Started
                    </a>
                </div>
            </div>
            
            <div class="hero-visual">
                <div class="visual-container">
                    <img src="assets/images/cityhall.jpeg" 
                         alt="Integrated Utility Dashboard Interface" class="img-responsive" 
                         style="width: 100%; height: 400px; object-fit: cover;">
                    <div class="visual-overlay"></div>
                </div>
            </div>
        </div>
    </section>

    <!-- Modules Showcase (Accurate to your System) -->
     <section class="modules-section" id="modules">
        <div class="section-header">
            <h2 class="section-title">Featured Services</h2>
            <p class="section-description">
                Explore and Manage utility operations.
            </p>
        </div>
        
        <!-- Desktop grid (hidden on mobile via CSS) -->
        <div class="modules-grid">
            <!-- 1. Asset Inventory -->
            <div class="module-card hover-lift">
                <div class="module-header">
                    <div class="module-icon icon-service">
                        <i class="fas fa-boxes"></i>
                    </div>
                    <h3 class="module-title">Asset Inventory</h3>
                </div>
                <p class="module-description">
                    Comprehensive database of all LGU-owned utility assets with GPS mapping, condition tracking, and full lifecycle management.
                </p>
                <ul class="module-features">
                    <li>GIS Mapping & Geotagging</li>
                    <li>Condition Monitoring</li>
                    <li>Lifecycle Tracking</li>
                    <li>Automated Health Alerts</li>
                </ul>
            </div>
            
            <!-- 2. Incident Reporting -->
            <div class="module-card hover-lift">
                <div class="module-header">
                    <div class="module-icon icon-complaint">
                        <i class="fas fa-exclamation-triangle"></i>
                    </div>
                    <h3 class="module-title">Incident Response</h3>
                </div>
                <p class="module-description">
                    Citizen-facing portal for reporting outages or leaks. Incidents are automatically categorized, prioritized, and routed to the right team.
                </p>
                <ul class="module-features">
                    <li>Geo-tagged Resident Reports</li>
                    <li>Automatic Triage & Priority</li>
                    <li>Real-time Status Tracking</li>
                    <li>Direct LGU Routing</li>
                </ul>
            </div>
            
            <!-- 3. Maintenance Dispatch -->
            <div class="module-card hover-lift">
                <div class="module-header">
                    <div class="module-icon icon-billing">
                        <i class="fas fa-tools"></i>
                    </div>
                    <h3 class="module-title">Maintenance Dispatch</h3>
                </div>
                <p class="module-description">
                    Seamless coordination with external maintenance systems to dispatch work orders and track repair progress in real-time.
                </p>
                <ul class="module-features">
                    <li>External Work Order Sync</li>
                    <li>Technician Dispatching</li>
                    <li>Progress Milestone Tracking</li>
                    <li>Service Level Auditing</li>
                </ul>
            </div>
            
            <!-- 4. Energy Efficiency -->
            <div class="module-card hover-lift">
                <div class="module-header">
                    <div class="module-icon icon-payment">
                        <i class="fas fa-bolt"></i>
                    </div>
                    <h3 class="module-title">Energy Efficiency</h3>
                </div>
                <p class="module-description">
                    Monitor electricity consumption across municipal facilities, identify anomalies, and sync data with external energy advisory systems.
                </p>
                <ul class="module-features">
                    <li>Real-time Consumption Logs</li>
                    <li>Anomaly Detection</li>
                    <li>Cost Analysis</li>
                    <li>External System Sync</li>
                </ul>
            </div>
            
            <!-- 5. Facility Management -->
            <div class="module-card hover-lift">
                <div class="module-header">
                    <div class="module-icon icon-reading">
                        <i class="fas fa-warehouse"></i>
                    </div>
                    <h3 class="module-title">Facility Management</h3>
                </div>
                <p class="module-description">
                    Track the utility readiness of public venues (gyms, parks, evacuation centers) to ensure they are operational when needed.
                </p>
                <ul class="module-features">
                    <li>Readiness Status Dashboard</li>
                    <li>Utility Checklist (Water/Elec)</li>
                    <li>Booking & Event Overlay</li>
                    <li>Incident Linking</li>
                </ul>
            </div>
            
            <!-- 6. Planning & Expansion -->
            <div class="module-card hover-lift">
                <div class="module-header">
                    <div class="module-icon icon-planning">
                        <i class="fas fa-map-marked-alt"></i>
                    </div>
                    <h3 class="module-title">Planning & Expansion</h3>
                </div>
                <p class="module-description">
                    Analyze utility coverage, manage expansion requests, and integrate with urban development projects to plan for future growth.
                </p>
                <ul class="module-features">
                    <li>GIS Coverage Mapping</li>
                    <li>Expansion Request Tracking</li>
                    <li>Capacity Planning</li>
                    <li>Project Integration</li>
                </ul>
            </div>
        </div>

        <!-- Mobile carousel (shown on mobile via CSS) -->
        <div class="modules-carousel-wrapper" id="modulesCarousel" aria-label="Featured Services Carousel">
            <div class="modules-carousel-track" id="modulesTrack">
                <div class="module-card">
                    <div class="module-header"><div class="module-icon icon-service"><i class="fas fa-boxes"></i></div><h3 class="module-title">Asset Inventory</h3></div>
                    <p class="module-description">Comprehensive database of all LGU-owned utility assets with GPS mapping, condition tracking, and full lifecycle management.</p>
                    <ul class="module-features"><li>GIS Mapping &amp; Geotagging</li><li>Condition Monitoring</li><li>Lifecycle Tracking</li><li>Automated Health Alerts</li></ul>
                </div>
                <div class="module-card">
                    <div class="module-header"><div class="module-icon icon-complaint"><i class="fas fa-exclamation-triangle"></i></div><h3 class="module-title">Incident Response</h3></div>
                    <p class="module-description">Citizen-facing portal for reporting outages or leaks. Incidents are automatically categorized, prioritized, and routed to the right team.</p>
                    <ul class="module-features"><li>Geo-tagged Resident Reports</li><li>Automatic Triage &amp; Priority</li><li>Real-time Status Tracking</li><li>Direct LGU Routing</li></ul>
                </div>
                <div class="module-card">
                    <div class="module-header"><div class="module-icon icon-billing"><i class="fas fa-tools"></i></div><h3 class="module-title">Maintenance Dispatch</h3></div>
                    <p class="module-description">Seamless coordination with external maintenance systems to dispatch work orders and track repair progress in real-time.</p>
                    <ul class="module-features"><li>External Work Order Sync</li><li>Technician Dispatching</li><li>Progress Milestone Tracking</li><li>Service Level Auditing</li></ul>
                </div>
                <div class="module-card">
                    <div class="module-header"><div class="module-icon icon-payment"><i class="fas fa-bolt"></i></div><h3 class="module-title">Energy Efficiency</h3></div>
                    <p class="module-description">Monitor electricity consumption across municipal facilities, identify anomalies, and sync data with external energy advisory systems.</p>
                    <ul class="module-features"><li>Real-time Consumption Logs</li><li>Anomaly Detection</li><li>Cost Analysis</li><li>External System Sync</li></ul>
                </div>
                <div class="module-card">
                    <div class="module-header"><div class="module-icon icon-reading"><i class="fas fa-warehouse"></i></div><h3 class="module-title">Facility Management</h3></div>
                    <p class="module-description">Track the utility readiness of public venues (gyms, parks, evacuation centers) to ensure they are operational when needed.</p>
                    <ul class="module-features"><li>Readiness Status Dashboard</li><li>Utility Checklist (Water/Elec)</li><li>Booking &amp; Event Overlay</li><li>Incident Linking</li></ul>
                </div>
                <div class="module-card">
                    <div class="module-header"><div class="module-icon icon-planning"><i class="fas fa-map-marked-alt"></i></div><h3 class="module-title">Planning &amp; Expansion</h3></div>
                    <p class="module-description">Analyze utility coverage, manage expansion requests, and integrate with urban development projects to plan for future growth.</p>
                    <ul class="module-features"><li>GIS Coverage Mapping</li><li>Expansion Request Tracking</li><li>Capacity Planning</li><li>Project Integration</li></ul>
                </div>
            </div>
            <!-- Dot indicators -->
            <div class="carousel-dots" id="carouselDots"></div>
        </div>
    </section>

    <!-- History -->
<section class="methodology-section" id="methodology">
    <div class="section-header">
        <h2 class="section-title">Our History</h2>
        <p class="section-description">
            Quezon City, officially established in 1939 with the vision of becoming the nation’s capital, has evolved from a sprawling agricultural landscape into the Philippines' most populous and vibrant urban center. Today, it serves over 3.1 million residents, hosts the seat of national government and major media hubs, and continues to lead the way in digital innovation, sustainable infrastructure, and inclusive public services.
        </p>
    </div>
    
    <div class="methodology-container">
        <div class="methodology-stack">
            
            <div class="method-card hover-lift" 
                 style="position: relative; width: 100%; height: 250px; 
                        background-image: url('assets/images/Manuel_Quezon.jpg'); 
                        background-size: cover; background-position: center; 
                        border-radius: 12px; color: white; 
                        display: flex; flex-direction: column; justify-content: flex-end; 
                        padding: 1rem; box-shadow: 0 4px 8px rgba(0,0,0,0.3); margin-bottom: 1rem;">
                <h3 style="margin: 0; font-size: 1.5rem; font-weight: bold; text-shadow: 1px 1px 5px rgba(0,0,0,0.7);">1939: The Foundation</h3>
                <p style="margin-top: 0.5rem; text-shadow: 1px 1px 4px rgba(0,0,0,0.7);">
                    President Manuel L. Quezon signed Commonwealth Act No. 502, officially creating Quezon City to serve as the new capital of the Philippines, envisioned as a "showcase of the nation" with wide avenues and open spaces.
                </p>
            </div>

            <div class="method-card hover-lift" 
                 style="position: relative; width: 100%; height: 250px; 
                        background-image: url('assets/images/QC.jpg'); 
                        background-size: cover; background-position: center; 
                        border-radius: 12px; color: white; 
                        display: flex; flex-direction: column; justify-content: flex-end; 
                        padding: 1rem; box-shadow: 0 4px 8px rgba(0,0,0,0.3); margin-bottom: 1rem;">
                <h3 style="margin: 0; font-size: 1.5rem; font-weight: bold; text-shadow: 1px 1px 5px rgba(0,0,0,0.7);">1948: The Capital Move</h3>
                <p style="margin-top: 0.5rem; text-shadow: 1px 1px 4px rgba(0,0,0,0.7);">
                    Republic Act No. 333 officially declared Quezon City as the capital of the Philippines. This era marked a significant northward expansion, absorbing territories from Caloocan and San Juan to accommodate growing government infrastructure.
                </p>
            </div>

            <div class="method-card hover-lift" 
                 style="position: relative; width: 100%; height: 250px; 
                        background-image: url('assets/images/The_Heart_of_Quezon_City.jpg'); 
                        background-size: cover; background-position: center; 
                        border-radius: 12px; color: white; 
                        display: flex; flex-direction: column; justify-content: flex-end; 
                        padding: 1rem; box-shadow: 0 4px 8px rgba(0,0,0,0.3);">
                <h3 style="margin: 0; font-size: 1.5rem; font-weight: bold; text-shadow: 1px 1px 5px rgba(0,0,0,0.7);">Today: The City of Stars</h3>
                <p style="margin-top: 0.5rem; text-shadow: 1px 1px 4px rgba(0,0,0,0.7);">
                    Now the most populous city in the Philippines, Quezon City serves as a premier hub for information technology, entertainment, and governance, continuing to modernize while prioritizing sustainable urban development and social services.
                </p>
            </div>

        </div>
    </div>
</section>

    <!-- Contact Section -->
    <section class="analytics-showcase" id="analytics">
        <div class="analytics-grid" style="grid-template-columns: 1fr; max-width: 900px; margin: 0 auto; padding: 0 2rem; text-align: center;">
            <div class="analytics-content" style="width: 100%;">
                <div class="section-preface" style="color: var(--insight-amber); text-align: center;">Contact Us</div>
                <h2 class="section-title" style="text-align: center;">Get in Touch with Us</h2>
                <p class="section-description" style="text-align: center; max-width: 600px; margin: 0 auto 2rem;">
                    Reach out with us for inquiries, concerns, or assistance. 
                    You can contact us through phone, email, or visit our location.
                </p>
                
                <div class="ai-insights" style="display: grid; grid-template-columns: repeat(auto-fit, minmax(240px, 1fr)); gap: 1.5rem; justify-content: center; width: 100%;">
                    <div class="insight-item hover-lift">
                        <div class="insight-header">
                            <div class="insight-icon">
                                <i class="fas fa-phone"></i>
                            </div>
                            <h4 class="insight-title">Phone</h4>
                        </div>
                        <p class="insight-description">
                           <a href="tel:+639128640498">+63 9 1286 40498</a>
                        </p>
                    </div>
                    
                    <div class="insight-item hover-lift">
                        <div class="insight-header">
                            <div class="insight-icon">
                                <i class="fas fa-envelope"></i>
                            </div>
                            <h4 class="insight-title">Email</h4>
                        </div>
                        <p class="insight-description">
                           <a href="mailto:lgu.uman@gmail.com">lgu.uman@gmail.com</a>
                        </p>
                    </div>
                    
                    <div class="insight-item hover-lift">
                        <div class="insight-header">
                            <div class="insight-icon">
                                <i class="fas fa-map-marker-alt"></i>
                            </div>
                            <h4 class="insight-title">Location</h4>
                        </div>
                        <p class="insight-description">
                            <a href="https://maps.app.goo.gl/rkT3Jmmf69kcpvgo7" 
                               target="_blank" style="color: var(--municipal-slate); text-decoration: underline;">
                                City Hall, Quezon City
                            </a>
                        </p>
                    </div>
                </div>
            </div>
        </div>
    </section>


    <!-- Footer -->
    <footer class="civic-footer">
        <div class="footer-grid">
            <!-- Column 1: Brand -->
            <div class="footer-brand">
                <div class="footer-logo">
                    <div class="footer-logo-icon" style="padding: 4px; overflow: hidden; display: flex; align-items: center; justify-content: center;">
                        <img src="assets/images/logocityhall.png" alt="QC Logo" style="width: 100%; height: 100%; object-fit: contain;">
                    </div>
                    <span class="footer-logo-text">Quezon City<br><small>Web-Based Utilities Management</small></span>
                </div>
                <p class="footer-description">
                    We streamline utility operations and provide easy access to monitor your consumption and service requests.
                </p>
            </div>
            
            <!-- Column 2: Quick Links -->
            <div>
                <h4 class="footer-heading">Quick Links</h4>
                <ul class="footer-links">
                    <li><a href="#hero">Home</a></li>
                    <li><a href="#modules">Modules</a></li>
                    <li><a href="#about">About</a></li>
                    <li><a href="#contacts">Contact</a></li>
                </ul>
            </div>
            
            <!-- Column 3: Core Services -->
            <div>
                <h4 class="footer-heading">Core Services</h4>
                <ul class="footer-links">
                    <li><a href="#modules">Asset Inventory</a></li>
                    <li><a href="#modules">Incident Reporting</a></li>
                    <li><a href="#modules">Maintenance Dispatch</a></li>
                    <li><a href="#modules">Energy Monitoring</a></li>
                </ul>
            </div>
            
            <!-- Column 4: Contact -->
            <div>
                <h4 class="footer-heading">Contact</h4>
                <ul class="footer-links">
                    <li>Email: <a href="mailto:info@lgu.gov.ph">info@lgu.gov.ph</a></li>
                    <li>Phone: <a href="tel:+63212345678">+63 2 1234 5678</a></li>
                    <li>Address: City Hall, Quezon City</li>
                    <li>
                        <a href="https://www.google.com/maps/place/Quezon+City+Hall" target="_blank">
                            <i class="fas fa-map-marker-alt"></i> Location
                        </a>
                    </li>
                </ul>
            </div>

            <!-- Column 5: Legal & Compliance -->
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
        function showLandingPage() {
            document.getElementById('landing-page')?.style.setProperty('display', 'block');
            document.getElementById('login-form')?.style.setProperty('display', 'none');
            document.getElementById('signup-form')?.style.setProperty('display', 'none');
            window.scrollTo(0, 0);
        }
        
        function showLoginForm() {
            document.getElementById('landing-page')?.style.setProperty('display', 'none');
            document.getElementById('login-form')?.style.setProperty('display', 'block');
            document.getElementById('signup-form')?.style.setProperty('display', 'none');
            window.scrollTo(0, 0);
        }
        
        function showSignupForm() {
            document.getElementById('landing-page')?.style.setProperty('display', 'none');
            document.getElementById('login-form')?.style.setProperty('display', 'none');
            document.getElementById('signup-form')?.style.setProperty('display', 'block');
            window.scrollTo(0, 0);
        }
        
        function togglePassword(inputId) {
            const input = document.getElementById(inputId);
            const icon = input.parentNode.querySelector('.toggle-password i');
            
            if (input.type === 'password') {
                input.type = 'text';
                icon.classList.remove('fa-eye');
                icon.classList.add('fa-eye-slash');
            } else {
                input.type = 'password';
                icon.classList.remove('fa-eye-slash');
                icon.classList.add('fa-eye');
            }
        }
        
        function checkPasswordStrength(password) {
            const strengthBar = document.getElementById('passwordStrength');
            let strength = 0;
            
            if (password.length >= 8) strength++;
            if (password.match(/[a-z]/)) strength++;
            if (password.match(/[A-Z]/)) strength++;
            if (password.match(/[0-9]/)) strength++;
            if (password.match(/[^a-zA-Z0-9]/)) strength++;
            
            strengthBar.className = 'password-strength';
            if (password.length === 0) {
                strengthBar.style.width = '0%';
                strengthBar.className = 'password-strength';
            } else if (strength <= 2) {
                strengthBar.className += ' strength-weak';
            } else if (strength <= 4) {
                strengthBar.className += ' strength-medium';
            } else {
                strengthBar.className += ' strength-strong';
            }
        }
        
        function checkPasswordMatch() {
            const password = document.getElementById('signupPassword').value;
            const confirmPassword = document.getElementById('confirmPassword').value;
            const matchText = document.getElementById('passwordMatchText');
            
            if (confirmPassword.length === 0) {
                matchText.innerHTML = '';
            } else if (password === confirmPassword) {
                matchText.innerHTML = '<span class="text-success fw-semibold"><i class="fas fa-check me-1"></i>Passwords match</span>';
            } else {
                matchText.innerHTML = '<span class="text-danger fw-semibold"><i class="fas fa-times me-1"></i>Passwords do not match</span>';
            }
        }
        
        document.getElementById('signupForm')?.addEventListener('submit', function(e) {
            const password = document.getElementById('signupPassword').value;
            const confirmPassword = document.getElementById('confirmPassword').value;
            
            if (password !== confirmPassword) {
                e.preventDefault();
                showNotification('Passwords do not match!', 'error');
                return false;
            }
            
            if (password.length < 8) {
                e.preventDefault();
                showNotification('Password must be at least 8 characters long!', 'error');
                return false;
            }
            
            const hasUpperCase = /[A-Z]/.test(password);
            const hasLowerCase = /[a-z]/.test(password);
            const hasNumbers = /\d/.test(password);
            
            if (!hasUpperCase || !hasLowerCase || !hasNumbers) {
                e.preventDefault();
                showNotification('Password must contain at least one uppercase letter, one lowercase letter, and one number!', 'error');
                return false;
            }
        });
        
        function showNotification(message, type) {
            const notification = document.createElement('div');
            notification.className = `alert alert-${type} alert-dismissible fade show position-fixed`;
            notification.style.cssText = 'top: 20px; right: 20px; z-index: 9999; min-width: 300px; border-radius: 12px;';
            notification.innerHTML = `
                <div class="d-flex align-items-center">
                    <i class="fas fa-${type === 'error' ? 'exclamation-triangle' : 'check-circle'} me-3 fs-5"></i>
                    <div class="flex-grow-1">${message}</div>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            `;
            document.body.appendChild(notification);
            
            setTimeout(() => {
                notification.remove();
            }, 5000);
        }
        
        setTimeout(function() {
            const alerts = document.querySelectorAll('.alert');
            alerts.forEach(function(alert) {
                if(window.bootstrap) {
                   const bsAlert = new bootstrap.Alert(alert);
                   bsAlert.close();
                } else {
                   alert.style.display = 'none';
                }
            });
        }, 5000);

        const mobileMenuBtn = document.getElementById('mobileMenuBtn');
        const navLinksList = document.getElementById('navLinksList');
        const mobileActionsGroup = document.querySelector('.mobile-nav-actions');

        mobileMenuBtn.addEventListener('click', () => {
            navLinksList.classList.toggle('active');
            const icon = mobileMenuBtn.querySelector('i');
            
            if(navLinksList.classList.contains('active')) {
                icon.className = 'fas fa-times';
                if(window.innerWidth <= 992) {
                    mobileActionsGroup.style.display = 'flex';
                }
            } else {
                icon.className = 'fas fa-bars';
                mobileActionsGroup.style.display = 'none';
            }
        });

        document.querySelectorAll('.nav-link').forEach(link => {
            link.addEventListener('click', () => {
                navLinksList.classList.remove('active');
                mobileMenuBtn.querySelector('i').className = 'fas fa-bars';
                mobileActionsGroup.style.display = 'none';
            });
        });

        window.addEventListener('resize', () => {
            if(window.innerWidth > 992) {
                navLinksList.classList.remove('active');
                mobileMenuBtn.querySelector('i').className = 'fas fa-bars';
                mobileActionsGroup.style.display = 'none';
            }
        });
        
        if(document.getElementById('landing-page')){
            showLandingPage();
        } else {
            window.scrollTo(0,0);
        }
    </script>

    <script>
        /* ── Featured Services Layered Stack Carousel (mobile only) ── */
        (function() {
            let carouselInitialized = false;

            function initCarousel() {
                if (window.innerWidth > 768) {
                    carouselInitialized = false;
                    return; // desktop: do nothing
                }
                if (carouselInitialized) return;

                const wrapper  = document.getElementById('modulesCarousel');
                const track    = document.getElementById('modulesTrack');
                const dotsBox  = document.getElementById('carouselDots');
                if (!wrapper || !track || !dotsBox) return;

                const cards    = Array.from(track.children);
                const total    = cards.length;
                let current    = 0;
                let autoTimer  = null;
                let startX     = 0;
                let startY     = 0;
                let isDragging = false;
                let currentX   = 0;

                carouselInitialized = true;

                // Build dot indicators
                dotsBox.innerHTML = '';
                cards.forEach((_, i) => {
                    const dot = document.createElement('button');
                    dot.className = 'carousel-dot' + (i === 0 ? ' active' : '');
                    dot.setAttribute('aria-label', 'Slide ' + (i + 1));
                    dot.addEventListener('click', () => {
                        stopAuto();
                        goTo(i);
                        startAuto();
                    });
                    dotsBox.appendChild(dot);
                });

                function updateStackClasses() {
                    cards.forEach((card, i) => {
                        card.className = 'module-card'; // reset classes
                        card.style.transform = '';     // clear inline dragging styles
                        card.style.opacity = '';
                        card.style.zIndex = '';
                        
                        const nextIndex = (current + 1) % total;
                        
                        if (i === current) {
                            card.classList.add('card-active');
                        } else if (i === nextIndex) {
                            card.classList.add('card-next');
                        }
                    });

                    dotsBox.querySelectorAll('.carousel-dot').forEach((d, i) => {
                        d.classList.toggle('active', i === current);
                    });
                }

                function goTo(index) {
                    current = (index + total) % total;
                    updateStackClasses();
                }

                function swipeSlide(direction) {
                    const activeCard = cards[current];
                    if (!activeCard) return;

                    // 1. Mark swiping card immediately
                    const swipeClass = direction === 'left' ? 'card-swiped-left' : 'card-swiped-right';
                    activeCard.className = 'module-card ' + swipeClass;
                    activeCard.style.zIndex = 4;
                    activeCard.style.opacity = 0;

                    // 2. Prep next cards instantly so they transition in parallel (prevents stuttering)
                    const nextCurrent = direction === 'left' 
                        ? (current + 1) % total 
                        : (current - 1 + total) % total;
                    
                    const nextNextIndex = (nextCurrent + 1) % total;

                    cards.forEach((card, i) => {
                        if (i === current) return; // Keep swipe class active
                        
                        card.style.transform = '';
                        card.style.opacity = '';
                        card.style.zIndex = '';
                        
                        if (i === nextCurrent) {
                            card.className = 'module-card card-active';
                        } else if (i === nextNextIndex) {
                            card.className = 'module-card card-next';
                        } else {
                            card.className = 'module-card';
                        }
                    });

                    // Update global index and dots instantly
                    current = nextCurrent;
                    dotsBox.querySelectorAll('.carousel-dot').forEach((d, i) => {
                        d.classList.toggle('active', i === current);
                    });

                    // 3. Clean up classes of the swiped away card after animation completes
                    const swipedCard = activeCard;
                    setTimeout(() => {
                        if (swipedCard.classList.contains('card-swiped-left') || swipedCard.classList.contains('card-swiped-right')) {
                            swipedCard.className = 'module-card';
                            swipedCard.style.transform = '';
                            swipedCard.style.opacity = '';
                            swipedCard.style.zIndex = '';
                        }
                    }, 450);
                }

                function nextSlide() {
                    swipeSlide('left');
                }

                function startAuto() {
                    stopAuto();
                    autoTimer = setInterval(nextSlide, 5000);
                }

                function stopAuto() {
                    if (autoTimer) { clearInterval(autoTimer); autoTimer = null; }
                }

                // Touch / Swipe controls
                function onTouchStart(e) {
                    const t = e.touches ? e.touches[0] : e;
                    startX = t.clientX;
                    startY = t.clientY;
                    isDragging = true;
                    currentX = 0;
                    stopAuto();
                    
                    const activeCard = cards[current];
                    if (activeCard) {
                        activeCard.style.transition = 'none';
                    }
                }

                function onTouchMove(e) {
                    if (!isDragging) return;
                    const t = e.touches ? e.touches[0] : e;
                    const diffX = t.clientX - startX;
                    const diffY = t.clientY - startY;

                    if (Math.abs(diffX) > Math.abs(diffY)) {
                        if (e.cancelable) e.preventDefault();
                    }

                    currentX = diffX;
                    const activeCard = cards[current];
                    if (activeCard) {
                        const rotation = diffX * 0.04;
                        activeCard.style.transform = `translate3d(${diffX}px, 0, 0) rotate(${rotation}deg)`;
                    }
                }

                function onTouchEnd() {
                    if (!isDragging) return;
                    isDragging = false;

                    const activeCard = cards[current];
                    if (!activeCard) return;

                    activeCard.style.transition = '';

                    if (Math.abs(currentX) > 80) {
                        // Perform swipe slide transition immediately
                        const direction = currentX > 0 ? 'right' : 'left';
                        swipeSlide(direction);
                    } else {
                        // Snap back
                        activeCard.style.transform = '';
                        startAuto();
                    }
                }

                // Bind touch events
                track.addEventListener('touchstart', onTouchStart, { passive: true });
                track.addEventListener('touchmove',  onTouchMove,  { passive: false });
                track.addEventListener('touchend',    onTouchEnd);

                // Bind mouse fallback events
                let mouseIsDown = false;
                track.addEventListener('mousedown', (e) => {
                    mouseIsDown = true;
                    onTouchStart(e);
                });
                window.addEventListener('mousemove', (e) => {
                    if (!mouseIsDown) return;
                    onTouchMove(e);
                });
                window.addEventListener('mouseup', (e) => {
                    if (!mouseIsDown) return;
                    mouseIsDown = false;
                    onTouchEnd();
                });

                goTo(0);
                startAuto();
            }

            document.addEventListener('DOMContentLoaded', initCarousel);
            window.addEventListener('resize', initCarousel);
            // Run immediately in case DOMContentLoaded already fired
            if (document.readyState === 'complete' || document.readyState === 'interactive') {
                initCarousel();
            }
        })();
    </script>
</body>
</html>
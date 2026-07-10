<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Barangay Pasong Putik Proper</title>
    <link rel="icon" type="image/png" href="logocityhall.png">
    <link href="https://cdn.jsdelivr.net/npm/@shoelace-style/shoelace@2.0.0-beta.83/dist/themes/light.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/luminous-lightbox@2.3.5/dist/luminous-basic.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/choices.js/public/assets/styles/choices.min.css">
    

    <style>

        :root {
            --civic-sapphire: #0B3D91;
            --utility-teal: #00A896;
            --insight-amber: #FF9E00;
            --municipal-slate: #2F4858;
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




        
        @keyframes shimmer {
            0% { transform: translateX(-100%); }
            100% { transform: translateX(100%); }
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
        }
        
        .ai-node {
            width: 12px;
            height: 12px;
            border-radius: 50%;
            background: var(--insight-amber);
            position: absolute;
            animation: pulse 2s infinite;
        }
        
        @keyframes pulse {
            0%, 100% { opacity: 1; transform: scale(1); }
            50% { opacity: 0.5; transform: scale(1.2); }
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
            border-left: 4px solid var(--insight-amber);
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
            background: rgba(255, 158, 0, 0.1);
            display: flex;
            align-items: center;
            justify-content: center;
            color: var(--insight-amber);
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
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 3rem;
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
            font-size: 1.5rem;
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
        
        .capstone-badge {
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            background: rgba(255, 255, 255, 0.1);
            color: white;
            padding: 0.5rem 1rem;
            border-radius: var(--radius-pill);
            font-size: 0.875rem;
            margin-top: 1rem;
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
        
        @media (max-width: 768px) {
            .nav-container {
                padding: 0 1rem;
            }
            
            .nav-links {
                display: none;
            }
            
            .hero-title {
                font-size: 2.25rem;
            }
            
            .modules-grid {
                grid-template-columns: 1fr;
                padding: 0 1rem;
            }
            
            .stat-panel {
                position: relative;
                bottom: auto;
                left: auto;
                right: auto;
                margin-top: 2rem;
            }
            
            .cta-actions {
                flex-direction: column;
                align-items: center;
            }
            
            .cta-button {
                width: 100%;
                justify-content: center;
            }
        }
        
        /* Micro-interactions */
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
                    <img src="logocityhall.png" alt="LGU Logo" class="logo-marker logo-only">

                    <i class="fas fa-bolt logo-icon"></i>
                </div>
                <div class="logo-text">
                    <span class="logo-primary">Barangay Pasong Putik</span>
                    <span class="logo-secondary"> <span class="title-gradient">Proper</span></span>
                </div>
            </a>
            
            <ul class="nav-links">
                <li class="nav-link-item"><a href="#hero" class="nav-link">Home</a></li>
                <li class="nav-link-item"><a href="#modules" class="nav-link">Utilities</a></li>
                <li class="nav-link-item"><a href="#methodology" class="nav-link">History</a></li>
                <li class="nav-link-item"><a href="#benefits" class="nav-link">Contacts</a></li>
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
                    Barangay Pasong Putik<br>
                    <span class="title-gradient">Web-Based Utilities Management System</span>
                </h1>
                
                <p class="hero-description">
                    Access barangay services anytime, anywhere. Manage your utilities, payments, and service requests online with real-time updates, AI insights, and secure access—designed for Pasok Putik Proper residents and staff.
                </p>
                
                <div class="cta-actions">
                    <a href="#modules" class="civic-button button-primary hover-lift">
                        <i class="fas fa-portal-entrance"></i>
                       Explore Utilities
                        <a href="login.php" class="civic-button button-secondary hover-lift">
                        <i class="fas fa-layer-group"></i>
                         Get Started
                    </a>
                </div>
            </div>
            
            <div class="hero-visual">
                <div class="visual-container">
                    <img src="assets/img/barangay.jpeg" 
                         alt="Integrated Utility Dashboard Interface" class="img-responsive" 
                         style="width: 100%; height: 400px; object-fit: cover;">
                    <div class="visual-overlay"></div>
                    

                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Modules Showcase -->
    <section class="modules-section" id="modules">
        <div class="section-header">
            <h2 class="section-title">Featured Services</h2>
            <p class="section-description">
                Explore and Manage Your Utilities
            </p>
        </div>
        
        <div class="modules-grid">
            <!-- Module 1 -->
            <div class="module-card hover-lift">
                <div class="module-header">
                    <div class="module-icon icon-reading">
                        <i class="fas fa-tachometer-alt"></i>
                    </div>
                    <h3 class="module-title">View and Pay Bills</h3>
                </div>
                <p class="module-description">
                    Access water, electricity, and other utility bills anytime.
                </p>
                <ul class="module-features">
                    <li>Digital reading input with validation</li>
                    <li>Consumption trend visualization</li>
                    <li>Usage pattern recognition</li>
                    <li>IoT-ready architecture</li>
                </ul>
            </div>
            
            <!-- Module 2 -->
            <div class="module-card hover-lift">
                <div class="module-header">
                    <div class="module-icon icon-billing">
                        <i class="fas fa-file-invoice-dollar"></i>
                    </div>
                    <h3 class="module-title">View and Pay Bills</h3>
                </div>
                <p class="module-description">
                    Easily access and settle your water, electricity, and other utility bills anytime, anywhere.
                </p>
                <ul class="module-features">
                    <li>Secure digital bill access and payment</li>
                    <li>Automatic meter reading integration</li>
                    <li>Penalty and discount application</li>
                    <li>Digital statement delivery</li>
                </ul>
            </div>
            
            <!-- Module 3 -->
            <div class="module-card hover-lift">
                <div class="module-header">
                    <div class="module-icon icon-payment">
                        <i class="fas fa-credit-card"></i>
                    </div>
                    <h3 class="module-title">Payment Collection & Tracking</h3>
                </div>
                <p class="module-description">
                    Multi-channel payment processing with real-time reconciliation 
                    and comprehensive transaction auditing.
                </p>
                <ul class="module-features">
                    <li>Multiple payment method support</li>
                    <li>Real-time status updates</li>
                    <li>Automated receipt generation</li>
                    <li>Collection efficiency analytics</li>
                </ul>
            </div>
            
            <!-- Module 4 -->
            <div class="module-card hover-lift">
                <div class="module-header">
                    <div class="module-icon icon-service">
                        <i class="fas fa-plug"></i>
                    </div>
                    <h3 class="module-title">Service Connection Management</h3>
                </div>
                <p class="module-description">
                    Streamlined processing of service requests including new 
                    connections, transfers, and scheduled disconnections.
                </p>
                <ul class="module-features">
                    <li>Online application submission</li>
                    <li>Request tracking system</li>
                    <li>Automated scheduling</li>
                    <li>Document management</li>
                </ul>
            </div>
            
            <!-- Module 5 -->
            <div class="module-card hover-lift">
                <div class="module-header">
                    <div class="module-icon icon-complaint">
                        <i class="fas fa-comment-dots"></i>
                    </div>
                    <h3 class="module-title">Complaint & Feedback Handling</h3>
                </div>
                <p class="module-description">
                    Centralized system for receiving, routing, and resolving 
                    utility-related complaints with performance analytics.
                </p>
                <ul class="module-features">
                    <li>Multi-channel complaint submission</li>
                    <li>Priority-based routing</li>
                    <li>Resolution timeline tracking</li>
                    <li>Service quality analytics</li>
                </ul>
            </div>
            
            <!-- AI Integration -->
            <div class="module-card hover-lift" style="border-left: 4px solid var(--insight-amber);">
                <div class="module-header">
                    <div class="module-icon" style="background: linear-gradient(135deg, var(--insight-amber), #FFB74D);">
                        <i class="fas fa-brain"></i>
                    </div>
                    <h3 class="module-title">AI-Powered Analytics Integration</h3>
                </div>
                <p class="module-description">
                    Predictive intelligence layer analyzing consumption patterns, 
                    detecting anomalies, and forecasting utility demands.
                </p>
                <ul class="module-features">
                    <li>Consumption anomaly detection</li>
                    <li>Demand forecasting models</li>
                    <li>Payment behavior analysis</li>
                    <li>Operational insight generation</li>
                </ul>
            </div>
        </div>
    </section>

<!-- History Section -->
<section class="methodology-section" id="methodology">
    <div class="section-header">
        <div class="section-preface">About the Barangay</div>
        <h2 class="section-title">Our History</h2>
        <p class="section-description">
            Barangay Pasok Putik Proper, officially established in 1996 after the division of the original Pasong Putik, has evolved from a rural community into a bustling urban hub. Today, it serves nearly 40,000 residents, hosts key transport hubs and commercial centers, and continues to focus on efficient governance and accessible public services.
        </p>
    </div>
    
    <div class="methodology-container">
        <div class="methodology-stack">
            
            <!-- 1948 Card -->
            <div class="method-card hover-lift" 
                 style="position: relative; width: 100%; height: 250px; 
                        background-image: url('assets/img/barangay.jpeg'); 
                        background-size: cover; background-position: center; 
                        border-radius: 12px; color: white; 
                        display: flex; flex-direction: column; justify-content: flex-end; 
                        padding: 1rem; box-shadow: 0 4px 8px rgba(0,0,0,0.3); margin-bottom: 1rem;">
                <h3 style="margin: 0; font-size: 1.5rem; font-weight: bold; text-shadow: 1px 1px 5px rgba(0,0,0,0.7);">1948</h3>
                <p style="margin-top: 0.5rem; text-shadow: 1px 1px 4px rgba(0,0,0,0.7);">
                    Original Pasong Putik included in Quezon City’s northward expansion into former Caloocan territory.
                </p>
            </div>

            <!-- 1996 Card -->
            <div class="method-card hover-lift" 
                 style="position: relative; width: 100%; height: 250px; 
                        background-image: url('assets/img/barangay.jpeg'); 
                        background-size: cover; background-position: center; 
                        border-radius: 12px; color: white; 
                        display: flex; flex-direction: column; justify-content: flex-end; 
                        padding: 1rem; box-shadow: 0 4px 8px rgba(0,0,0,0.3); margin-bottom: 1rem;">
                <h3 style="margin: 0; font-size: 1.5rem; font-weight: bold; text-shadow: 1px 1px 5px rgba(0,0,0,0.7);">1996</h3>
                <p style="margin-top: 0.5rem; text-shadow: 1px 1px 4px rgba(0,0,0,0.7);">
                    Barangay Pasok Putik Proper officially established after the division of Pasong Putik, along with Greater Lagro and North Fairview, ratified through plebiscite.
                </p>
            </div>

            <!-- Today Card -->
            <div class="method-card hover-lift" 
                 style="position: relative; width: 100%; height: 250px; 
                        background-image: url('assets/img/barangay.jpeg'); 
                        background-size: cover; background-position: center; 
                        border-radius: 12px; color: white; 
                        display: flex; flex-direction: column; justify-content: flex-end; 
                        padding: 1rem; box-shadow: 0 4px 8px rgba(0,0,0,0.3);">
                <h3 style="margin: 0; font-size: 1.5rem; font-weight: bold; text-shadow: 1px 1px 5px rgba(0,0,0,0.7);">Today</h3>
                <p style="margin-top: 0.5rem; text-shadow: 1px 1px 4px rgba(0,0,0,0.7);">
                    Continues to prioritize efficient governance, public service accessibility, and modern urban solutions for all residents.
                </p>
            </div>

        </div>
    </div>
</section>


    <!-- AI Analytics Showcase -->
    <section class="analytics-showcase" id="analytics">
        <div class="analytics-grid">
            <div class="analytics-content">
                <div class="section-preface" style="color: var(--insight-amber);">Contact Us</div>
                <h2 class="section-title">Get in Touch with Us</h2>
                <p class="section-description">
                    Reach out with us for inquiries, concerns, or assistance. 
                You can contact us through phone, email, or visit our location.
                </p>
                
                <div class="ai-insights">
                    <div class="insight-item hover-lift">
                        <div class="insight-header">
                            <div class="insight-icon">
                                <i class="fas fa-exclamation-triangle"></i>
                            </div>
                            <h4 class="insight-title">Phone</h4>
                        </div>
                        <p class="insight-description">
                           09128640498
                        </p>
                    </div>
                    
                    <div class="insight-item hover-lift">
                        <div class="insight-header">
                            <div class="insight-icon">
                                <i class="fas fa-chart-line"></i>
                            </div>
                            <h4 class="insight-title">Email</h4>
                        </div>
                        <p class="insight-description">
                            lgu.uman@gmail.com
                        </p>
                    </div>
                    
                    <div class="insight-item hover-lift">
                        <div class="insight-header">
                            <div class="insight-icon">
                                <i class="fas fa-user-clock"></i>
                            </div>
                            <h4 class="insight-title">Location</h4>
                        </div>
                        <p class="insight-description">
                            <a href="https://www.google.com/maps/place/Barangay+Pasong+Putik+Proper,+Quezon+City" 
                           target="_blank" style="color: var(--municipal-slate); text-decoration: underline;">
                            View on Google Maps
                        </p>
                    </div>
                </div>
            </div>
            
        <div class="analytics-visual">
            <div class="ai-visual" style="border-radius: 16px; overflow: hidden; height: 100%; min-height: 300px;">
                <!-- Google Maps Embed -->
                <iframe 
                    src="https://www.google.com/maps/embed?pb=!1m18!1m12!1m3!1d3875.123456789!2d121.049987!3d14.655123!2m3!1f0!2f0!3f0!3m2!1i1024!2i768!4f13.1!3m3!1m2!1s0x3397b9e2f2f1d123%3A0xabcdef1234567890!2sBarangay+Pasong+Putik+Proper!5e0!3m2!1sen!2sph!4v1680000000000!5m2!1sen!2sph" 
                    width="100%" 
                    height="100%" 
                    style="border:0;" 
                    allowfullscreen="" 
                    loading="lazy" 
                    referrerpolicy="no-referrer-when-downgrade">
                </iframe>
            </div>
        </div>
    </div>
    </section>

    <!-- CTA Section -->
    <section class="cta-section" id="cta">
        <div class="cta-container">
            <h2 class="cta-title">Ready to Transform Utility Management?</h2>
            <p class="cta-description">
                Join LGU 1 in pioneering intelligent utility service delivery through 
                integrated digital transformation and predictive analytics.
            </p>
            
            <div class="cta-actions">
                <a href="login.php" class="civic-button cta-button-light hover-lift">
                    <i class="fas fa-user-check"></i>
                    Resident Portal Access
                </a>
                <a href="admin/login.php" class="civic-button cta-button-outline hover-lift">
                    <i class="fas fa-lock"></i>
                    Administrative Login
                </a>
            </div>
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
                    <span class="footer-logo-text">Barangay Pasong Putik Proper <h6>Web-Based Utilities Management System</h6></span>
                </div>
                <p class="footer-description">
                    Access barangay services anytime, anywhere. Manage your utilities, payments, and service requests online with real-time updates.
                </p>
            </div>
            <!-- Quick Links -->
        <div>
            <h4 class="footer-heading">Quick Links</h4>
            <ul class="footer-links">
                <li><a href="#hero">Home</a></li>
                <li><a href="#modules">Utilities</a></li>
                <li><a href="#methodology">History</a></li>
                <li><a href="#analytics">Contacts</a></li>
            </ul>
        </div>
            
        <!-- Services -->
        <div>
            <h4 class="footer-heading">Services</h4>
            <ul class="footer-links">
                <li><a href="#modules">View & Pay Bills</a></li>
                <li><a href="#modules">Payment Collection</a></li>
                <li><a href="#modules">Service Connection</a></li>
                <li><a href="#modules">Complaint Handling</a></li>
            </ul>
        </div>
            
      <!-- Contact / Social -->
        <div>
            <h4 class="footer-heading">Contact Us</h4>
            <ul class="footer-links">
                <li>Email: <a href="mailto:info@pasokputik.gov.ph">info@pasokputik.gov.ph</a></li>
                <li>Phone: <a href="tel:+63212345678">+63 2 1234 5678</a></li>
                <li>Address: Barangay Hall, Pasok Putik Proper, Quezon City</li>
                <li>
                    <a href="https://www.google.com/maps/place/Barangay+Pasong+Putik+Proper,+Quezon+City" target="_blank">
    <i class="fas fa-map-marker-alt"></i> Location
</a>

                </li>
            </ul>
        </div>
    </div>

<!-- Live Chat Section -->
<section class="live-chat-section" id="live-chat-section">
    <div class="live-chat-container">
        <div class="chat-header" onclick="toggleChat()">
            <i class="fas fa-comment-dots"></i> Chat with Barangay
        </div>
        <div class="chat-body">
            <div class="chat-messages" id="chat-messages"></div>
            <div class="chat-input-area">
                <input type="text" id="chat-input" placeholder="Type your message..." onkeypress="handleKeyPress(event)">
                <button onclick="sendMessage()">Send</button>
            </div>
        </div>
    </div>
</section>

<style>
    .live-chat-section {
        position: fixed;
        bottom: 20px;
        right: 20px;
        width: 320px;
        max-width: 95%;
        font-family: 'Poppins', sans-serif;
        z-index: 9999;
    }

    .live-chat-container {
        border-radius: 16px;
        overflow: hidden;
        box-shadow: 0 8px 24px rgba(0,0,0,0.2);
        display: flex;
        flex-direction: column;
    }

    .chat-header {
        background: linear-gradient(90deg, #f59e0b, #d97706);
        color: white;
        padding: 14px;
        font-weight: 600;
        cursor: pointer;
        display: flex;
        align-items: center;
        gap: 10px;
        font-size: 16px;
    }

    .chat-body {
        display: none;
        flex-direction: column;
        background-color: #fff7ed;
        max-height: 400px;
        border-top: 2px solid #0b70f5;
    }

    .chat-messages {
        flex: 1;
        padding: 12px;
        overflow-y: auto;
        font-size: 14px;
        display: flex;
        flex-direction: column;
        gap: 12px;
    }

    .message {
        padding: 10px 14px;
        border-radius: 20px;
        max-width: 75%;
        word-wrap: break-word;
        line-height: 1.4;
        font-size: 14px;
        display: flex;
        align-items: flex-end;
        gap: 8px;
    }

    /* User messages (right side) */
    .message.user {
        background-color: #f59e0b;
        color: white;
        align-self: flex-end;
        border-bottom-right-radius: 4px;
        border-bottom-left-radius: 20px;
        justify-content: flex-end;
    }

    /* Bot messages (left side) */
    .message.bot {
        background-color: #ffffff;
        color: #333;
        align-self: flex-start;
        border-bottom-left-radius: 4px;
        border-bottom-right-radius: 20px;
        justify-content: flex-start;
    }

    /* Robot icon for bot messages */
    .message.bot .icon {
        width: 24px;
        height: 24px;
        display: flex;
        align-items: center;
        justify-content: center;
        flex-shrink: 0;
        font-size: 18px;
        color: #f59e0b;
    }

    .chat-input-area {
        display: flex;
        border-top: 2px solid #f59e0b;
        background-color: #fff7ed;
    }

    #chat-input {
        flex: 1;
        padding: 10px;
        border: none;
        outline: none;
        font-size: 14px;
    }

    .chat-input-area button {
        background: #d97706;
        color: white;
        border: none;
        padding: 10px 14px;
        cursor: pointer;
        font-weight: 600;
        transition: 0.2s;
    }

    .chat-input-area button:hover {
        background: #b45309;
    }

    @media(max-width: 480px) {
        .live-chat-section {
            width: 90%;
            bottom: 10px;
            right: 5%;
        }
    }
</style>

<script>
    const chatBody = document.querySelector('.chat-body');
    const chatMessages = document.getElementById('chat-messages');
    const chatInput = document.getElementById('chat-input');

    function toggleChat() {
        chatBody.style.display = chatBody.style.display === 'flex' ? 'none' : 'flex';
    }

    function sendMessage() {
        const msg = chatInput.value.trim();
        if(msg === "") return;

        appendMessage(msg, 'user');
        chatInput.value = '';

        // Simulated bot response
        setTimeout(() => {
            appendMessage('Hello! How may we assist you today?', 'bot');
        }, 500);
    }

    function appendMessage(message, sender) {
        const msgDiv = document.createElement('div');
        msgDiv.classList.add('message', sender);

        if(sender === 'bot') {
            const iconDiv = document.createElement('div');
            iconDiv.classList.add('icon');
            iconDiv.innerHTML = '<i class="fas fa-robot"></i>';
            msgDiv.appendChild(iconDiv);
        }

        const textDiv = document.createElement('div');
        textDiv.textContent = message;
        msgDiv.appendChild(textDiv);

        chatMessages.appendChild(msgDiv);
        chatMessages.scrollTop = chatMessages.scrollHeight;
    }

    function handleKeyPress(event) {
        if(event.key === 'Enter') sendMessage();
    }
</script>

<li><a href="live_chat.php">Live Chat</a></li>

    
        <div class="footer-bottom">
            <div class="footer-copyright">
                 &copy; 2026 Barangay Pasong Putik Proper · All Rights Reserved
            </div>
        </div>
    </footer>

    <!-- Scripts -->
    <script src="https://cdn.jsdelivr.net/npm/@shoelace-style/shoelace@2.0.0-beta.83/dist/shoelace.js" type="module"></script>
    <script src="https://cdn.jsdelivr.net/npm/luminous-lightbox@2.3.5/dist/Luminous.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/choices.js/public/assets/scripts/choices.min.js"></script>
    <script src="https://kit.fontawesome.com/your-fontawesome-kit.js" crossorigin="anonymous"></script>
    
    <script>
        // Show landing page
        function showLandingPage() {
            document.getElementById('landing-page').style.display = 'block';
            document.getElementById('login-form').style.display = 'none';
            document.getElementById('signup-form').style.display = 'none';
            window.scrollTo(0, 0);
        }
        
        // Show login form
        function showLoginForm() {
            document.getElementById('landing-page').style.display = 'none';
            document.getElementById('login-form').style.display = 'block';
            document.getElementById('signup-form').style.display = 'none';
            window.scrollTo(0, 0);
        }
        
        // Show signup form
        function showSignupForm() {
            document.getElementById('landing-page').style.display = 'none';
            document.getElementById('login-form').style.display = 'none';
            document.getElementById('signup-form').style.display = 'block';
            window.scrollTo(0, 0);
        }
        
        // Toggle password visibility
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
        
        // Check password strength
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
        
        // Check password match
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
        
        // Form validation for signup
        document.getElementById('signupForm').addEventListener('submit', function(e) {
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
        
        // Custom notification function
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
        
        // Auto-hide alerts after 5 seconds
        setTimeout(function() {
            const alerts = document.querySelectorAll('.alert');
            alerts.forEach(function(alert) {
                const bsAlert = new bootstrap.Alert(alert);
                bsAlert.close();
            });
        }, 5000);
        
        // Initialize the page
        showLandingPage();
    </script>
</body>
</html>
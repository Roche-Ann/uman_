<?php if (isset($_SESSION['user_id'])): ?>
            </main>
        </div>
    </div>
    <?php endif; ?>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
    <script src="/lgu-urban-planning/assets/js/main.js"></script>

    <style>
.page-footer { width: 100%; padding: 12px 45px; background: rgba(var(--bs-body-bg-rgb), 0.15); backdrop-filter: blur(18px); -webkit-backdrop-filter: blur(18px); border-top: 1px solid rgba(var(--bs-border-color-rgb), 0.25); box-shadow: 0 -4px 25px rgba(0,0,0,0.15); position: fixed; bottom: 0; left: 0; z-index: 1000; animation: fadeUp 0.6s ease-out; }
.footer-container { display: flex; justify-content: center; align-items: center; gap: 15px; color: #ffffff; font-size: 0.85rem; }
.footer-container a { color: #ffffff; text-decoration: none; opacity: 0.8; font-weight: 500; transition: 0.3s; }
.footer-container a:hover { opacity: 1; color: var(--bs-primary); text-shadow: 0 0 10px rgba(var(--bs-primary-rgb), 0.3); }
.footer-container .separator { opacity: 0.6; color: #ffffff; }
body { padding-bottom: 70px; }

/* MOBILE RESPONSIVE */
@media (max-width: 768px) { .page-footer { padding: 12px 20px; } }
@media (max-width: 768px) { .footer-container { gap: 12px; font-size: 0.8rem; } }
@media (max-width: 768px) { .modal-dialog { max-width: 90%; margin: 1.75rem auto; } }

@media (max-width: 480px) { .page-footer { padding: 10px 5px; height: auto; } }
@media (max-width: 480px) { .footer-container { flex-wrap: wrap; gap: 5px 10px; justify-content: center; font-size: 0.75rem; } }
@media (max-width: 480px) { .footer-container .separator { display: none; } }
@media (max-width: 480px) { body { padding-bottom: 90px; } }

@media (max-width: 320px) { .footer-container { flex-direction: column; gap: 2px; text-align: center; } }
@media (max-width: 320px) { .footer-container a { font-size: 0.7rem; display: block; padding: 2px 0; } }
@media (max-width: 320px) { body { padding-bottom: 110px; } }
@media (max-width: 320px) { .modal-body { padding: 15px !important; font-size: 0.8rem !important; } }
@media (max-width: 320px) { .modal-header h5 { font-size: 0.9rem; } }
</style>

    <footer class="page-footer">
    <div class="footer-container">
        <a href="#" data-bs-toggle="modal" data-bs-target="#privacyModal" data-en="Privacy Policy" data-tl="Patakaran sa Privacy">Privacy Policy</a>
        <span class="separator">•</span>
        <a href="#" data-bs-toggle="modal" data-bs-target="#termsModal" data-en="Terms of Service" data-tl="Kasunduan sa Serbisyo">Terms of Service</a>
        <span class="separator">•</span>
        <a href="#" data-bs-toggle="modal" data-bs-target="#helpdeskModal" data-en="Contact Support" data-tl="Kontakin ang Support">Contact Support</a>
        </div>
    </div>
</footer>

<div class="modal fade" id="privacyModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content border-0 shadow bg-body">
            <div class="modal-header border-bottom border-secondary-subtle">
                <h5 class="modal-title fw-bold text-primary">
                    <i class="bi bi-shield-lock me-2"></i>
                    <span data-en="Data Privacy Policy" data-tl="Patakaran sa Privacy ng Datos">Data Privacy Policy</span>
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>

            <div class="modal-body p-4 text-body" style="font-size: 0.9rem; line-height: 1.6;">
                
                <div data-en-block class="lang-block">
                    <p class="fw-bold text-primary">I. Commitment to Data Privacy</p>
                    <p>The Local Government Unit (LGU) is committed to protecting the privacy of its stakeholders and ensuring that all personal data collected through the Urban Planning and Development Permit Management System are processed in accordance with <strong>Republic Act No. 10173</strong>, otherwise known as the <strong>Data Privacy Act of 2012</strong>.</p>
                    
                    <p class="fw-bold text-primary">II. Collection and Use of Personal Information</p>
                    <p>We collect personal information solely for the purpose of processing development permits, verifying identity, and official communication regarding urban planning applications. This may include, but is not limited to, names, contact details, and property documents.</p>
                    
                    <p class="fw-bold text-primary">III. Security Measures</p>
                    <p>Strict organizational, physical, and technical security measures are implemented to protect your data against unauthorized access, alteration, or disclosure. Only authorized LGU personnel are granted access to your information.</p>
                </div>

                <div data-tl-block class="lang-block" style="display: none;">
                    <p class="fw-bold text-primary">I. Komitment sa Privacy ng Datos</p>
                    <p>Ang Pamahalaang Lokal (LGU) ay nakatuon sa pagprotekta sa privacy ng mga mamamayan at sinisiguro na lahat ng impormasyong kinukuha sa pamamagitan ng Urban Planning and Development Permit Management System ay pinoproseso alinsunod sa <strong>Republic Act No. 10173</strong> o ang <strong>Data Privacy Act of 2012</strong>.</p>
                    
                    <p class="fw-bold text-primary">II. Pagkolekta at Paggamit ng Impormasyon</p>
                    <p>Kinokolekta namin ang inyong impormasyon para lamang sa pagproseso ng mga development permit, pagpapatunay ng inyong pagkakakilanlan, at opisyal na komunikasyon ukol sa inyong aplikasyon. Kasama rito ang pangalan, detalye ng kontak, at mga dokumento ng ari-arian.</p>
                    
                    <p class="fw-bold text-primary">III. Mga Hakbang sa Seguridad</p>
                    <p>Nagpapatupad kami ng mahigpit na teknikal at pisikal na seguridad upang maprotektahan ang inyong datos laban sa hindi awtorisadong pag-access o pagbabago. Tanging mga awtorisadong tauhan lamang ng LGU ang may pahintulot na makita ang inyong impormasyon.</p>
                </div>

            </div> <div class="modal-footer border-top border-secondary-subtle">
                <button type="button" class="btn btn-secondary btn-sm px-4" data-bs-dismiss="modal" data-en="Close" data-tl="Isara">Close</button>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="termsModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content border-0 shadow bg-body">
            <div class="modal-header border-bottom border-secondary-subtle">
                <h5 class="modal-title fw-bold text-primary">
                    <i class="bi bi-file-earmark-text me-2"></i>
                    <span data-en="Terms of Service" data-tl="Kasunduan sa Serbisyo">Terms of Service</span>
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>

            <div class="modal-body p-4 text-body" style="font-size: 0.9rem; line-height: 1.6;">
                
                <div data-en-block class="lang-block">
                    <p>By accessing and using this portal, you agree to the following terms:</p>
                    
                    <p class="fw-bold text-primary mb-1">1. Accuracy of Information</p>
                    <p>Users are responsible for ensuring all submitted documents and data are true, accurate, and up-to-date. Any falsification of public documents is subject to legal action under the Revised Penal Code.</p>
                    
                    <p class="fw-bold text-primary mb-1">2. Proper Use of Portal</p>
                    <p>This system shall be used exclusively for official urban planning and development permit applications. Unauthorized attempts to bypass security or modify data are strictly prohibited.</p>
                    
                    <p class="fw-bold text-primary mb-1">3. Compliance</p>
                    <p>Applications are subject to the National Building Code of the Philippines, local zoning ordinances, and other relevant environmental and safety regulations.</p>
                </div>

                <div data-tl-block class="lang-block" style="display: none;">
                    <p>Sa paggamit ng portal na ito, sumasang-ayon ka sa mga sumusunod na tuntunin:</p>
                    
                    <p class="fw-bold text-primary mb-1">1. Katumpakan ng Impormasyon</p>
                    <p>Responsibilidad ng gumagamit na tiyakin na lahat ng dokumento at datos na ipapasa ay totoo at wasto. Ang anumang maling impormasyon o pamemeke ng dokumento ay may kaukulang parusang legal.</p>
                    
                    <p class="fw-bold text-primary mb-1">2. Tamang Paggamit ng Portal</p>
                    <p>Ang sistemang ito ay dapat gamitin lamang para sa opisyal na aplikasyon ng development permit. Mahigpit na ipinagbabawal ang anumang pagtatangka na sirain ang seguridad ng system.</p>
                    
                    <p class="fw-bold text-primary mb-1">3. Pagsunod sa Batas</p>
                    <p>Ang bawat aplikasyon ay dadaan sa pagsusuri batay sa National Building Code, lokal na ordinansa sa zoning, at iba pang regulasyong pang-kaligtasan.</p>
                </div>

            </div>

            <div class="modal-footer border-top border-secondary-subtle">
                <button type="button" class="btn btn-secondary btn-sm px-4" data-bs-dismiss="modal" data-en="Close" data-tl="Isara">Close</button>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="helpdeskModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow bg-body">
            <div class="modal-header border-bottom border-secondary-subtle">
                <h5 class="modal-title fw-bold text-primary">
                    <i class="bi bi-headset me-2"></i>
                    <span data-en="Contact Support" data-tl="Kontakin ang Support">Contact Support</span>
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>

            <div class="modal-body p-4 text-body">
                <div data-en-block class="lang-block">
                    <p class="text-body-secondary small mb-4">For inquiries regarding urban planning, zoning, and development permits, please contact:</p>
                    
                    <h6 class="fw-bold mb-1">City Planning and Development Department (CPDD)</h6>
                    <p class="mb-3" style="font-size: 0.9rem;">
                        <i class="bi bi-geo-alt text-danger me-2"></i>7th Floor, Civic Center Bldg. A, QC Hall Complex<br>
                        <i class="bi bi-envelope text-primary me-2"></i>cpdd@quezoncity.gov.ph<br>
                        <i class="bi bi-telephone text-success me-2"></i>(02) 8988-4242 loc. 1400 / 1404
                    </p>

                    <h6 class="fw-bold mb-1">Office Hours</h6>
                    <p class="mb-0" style="font-size: 0.9rem;">
                        <i class="bi bi-clock me-2"></i>Monday – Friday, 8:00 AM – 5:00 PM
                    </p>
                </div>

                <div data-tl-block class="lang-block" style="display: none;">
                    <p class="text-body-secondary small mb-4">Para sa mga katanungan tungkol sa urban planning, zoning, at development permits, maaaring makipag-ugnayan sa:</p>
                    
                    <h6 class="fw-bold mb-1">City Planning and Development Department (CPDD)</h6>
                    <p class="mb-3" style="font-size: 0.9rem;">
                        <i class="bi bi-geo-alt text-danger me-2"></i>7th Floor, Civic Center Bldg. A, QC Hall Complex<br>
                        <i class="bi bi-envelope text-primary me-2"></i>cpdd@quezoncity.gov.ph<br>
                        <i class="bi bi-telephone text-success me-2"></i>(02) 8988-4242 loc. 1400 / 1404
                    </p>

                    <h6 class="fw-bold mb-1">Oras ng Tanggapan</h6>
                    <p class="mb-0" style="font-size: 0.9rem;">
                        <i class="bi bi-clock me-2"></i>Lunes – Biyernes, 8:00 AM – 5:00 PM
                    </p>
                </div>
            </div>

            <div class="modal-footer border-top border-secondary-subtle">
                <button type="button" class="btn btn-secondary btn-sm px-4" data-bs-dismiss="modal" data-en="Close" data-tl="Isara">Close</button>
            </div>
        </div>
    </div>
</div>

</body>
</html>
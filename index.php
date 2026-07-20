<?php include 'header.php'; ?>

<main>
    <section style="width: 100%;">
        <img src="assets/images/home-banner.svg" alt="CPVIA Banner" style="width: 100%; display: block; height: auto;">
    </section>

    <!-- Stats Banner -->
    <section class="stats-banner">
        <!-- Background Curve SVG -->
        <svg class="stats-curve" viewBox="0 0 1440 250" preserveAspectRatio="none" fill="none"
            xmlns="http://www.w3.org/2000/svg">
            <path d="M0 180 C 300 -50, 1000 300, 1440 100" stroke="#FFFFFF" stroke-opacity="0.05" stroke-width="125" />
        </svg>

        <div class="stat-item">
            <h2>175+</h2>
            <p>YEARS COMBINED EXPERIENCE</p>
        </div>
        <div class="stat-item">
            <h2>20+</h2>
            <p>EXPERT TEAM MEMBERS</p>
        </div>
        <div class="stat-item">
            <h2>6</h2>
            <p>CORE SERVICE AREAS</p>
        </div>
        <div class="stat-item">
            <h2>11+</h2>
            <p>THERAPEUTIC AREAS</p>
        </div>
    </section>

    <!-- About Section -->
    <section class="about-section">
        <div class="about-img-col">
            <img src="assets/images/home-about.webp" alt="CPVIA Team" class="about-img">
        </div>
        <div class="about-text-col">
            <div class="section-badge orange"><span class="line orange-line"></span> ABOUT CPVIA</div>

            <h2 class="about-heading">
                <span class="dark-text">Biometrics & Medical Device-Oriented</span><br>
                <span class="highlight-purple underline-orange">Clinical Research</span> <span
                    class="highlight-purple">Solutions</span>
            </h2>

            <p class="about-desc">CPVIA is headquartered in Hyderabad, India — a globally recognized clinical research
                hub. We specialize in end-to-end biometrics and medical device services for pharmaceutical,
                biotechnology, CRO, and medical
                device companies worldwide.</p>

            <p class="about-quote">"Bringing all biometrics and medical device expertise in clinical research under one
                platform."</p>

            <p class="about-desc">Our mission is to deliver a one-stop solution for clinical development — with
                measurable value, precision execution, and regulatory excellence at every stage.</p>

            <div class="about-features-grid">
                <div class="feature-card">
                    <div class="icon orange-icon"><svg width="18" height="18" viewBox="0 0 24 24" fill="none"
                            stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <circle cx="12" cy="12" r="10"></circle>
                            <line x1="2" y1="12" x2="22" y2="12"></line>
                            <path
                                d="M12 2a15.3 15.3 0 0 1 4 10 15.3 15.3 0 0 1-4 10 15.3 15.3 0 0 1-4-10 15.3 15.3 0 0 1 4-10z">
                            </path>
                        </svg></div>
                    <span>Global Pharma & CRO Support</span>
                </div>
                <div class="feature-card">
                    <div class="icon purple-icon"><svg width="18" height="18" viewBox="0 0 24 24" fill="none"
                            stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <rect x="3" y="3" width="18" height="18" rx="2" ry="2"></rect>
                            <polyline points="9 12 12 15 16 9"></polyline>
                        </svg></div>
                    <span>Regulatory Submission Expertise</span>
                </div>
                <div class="feature-card">
                    <div class="icon orange-icon"><svg width="18" height="18" viewBox="0 0 24 24" fill="none"
                            stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <polygon points="13 2 3 14 12 14 11 22 21 10 12 10 13 2"></polygon>
                        </svg></div>
                    <span>Innovation & Automation Driven</span>
                </div>
                <div class="feature-card">
                    <div class="icon purple-icon"><svg width="18" height="18" viewBox="0 0 24 24" fill="none"
                            stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"></path>
                            <circle cx="9" cy="7" r="4"></circle>
                            <path d="M23 21v-2a4 4 0 0 0-3-3.87"></path>
                            <path d="M16 3.13a4 4 0 0 1 0 7.75"></path>
                        </svg></div>
                    <span>Experienced Biometrics & Medical Device Team</span>
                </div>
                <div class="feature-card">
                    <div class="icon orange-icon"><svg width="18" height="18" viewBox="0 0 24 24" fill="none"
                            stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <polyline points="22 12 18 12 15 21 9 3 6 12 2 12"></polyline>
                        </svg></div>
                    <span>SAS, R & Python Expert Teams</span>
                </div>
            </div>
        </div>
    </section>

    <!-- Gradient Divider -->
    <div class="gradient-divider"></div>

    <!-- Services Section -->
    <section class="services-section">
        <div class="section-badge orange"><span class="line orange-line"></span> CORE SERVICES</div>

        <h2 class="services-heading">
            <span class="dark-text">Comprehensive</span> <span class="highlight-purple underline-orange">Biometrics &
                Medical Device</span><br>
            <span class="dark-text">Service Portfolio</span>
        </h2>

        <p class="services-desc">End-to-end clinical research solutions designed for<br>pharmaceutical, biotech, and CRO
            partners across all therapeutic<br>areas and study phases.</p>

        <div class="services-carousel-wrapper">
            <button class="carousel-arrow prev" id="carousel-prev" aria-label="Previous slide">
                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3"
                    stroke-linecap="round" stroke-linejoin="round">
                    <line x1="19" y1="12" x2="5" y2="12"></line>
                    <polyline points="12 19 5 12 12 5"></polyline>
                </svg>
            </button>
            <button class="carousel-arrow next" id="carousel-next" aria-label="Next slide">
                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3"
                    stroke-linecap="round" stroke-linejoin="round">
                    <line x1="5" y1="12" x2="19" y2="12"></line>
                    <polyline points="12 5 19 12 12 19"></polyline>
                </svg>
            </button>
            <div class="acc-services-container" id="services-carousel-track">
                <!-- Card 1 -->
                <div class="acc-service-card" style="background-image: url('assets/images/CPVIA_0005_Biostatistics.webp');">
                    <div class="acc-overlay"></div>
                    <div class="acc-content">
                        <h3>Biostatistics</h3>
                        <h4>Advanced statistical methodologies</h4>
                        <div class="acc-hidden-content">
                            <p>Advanced statistical methodologies and clinical trial analytics — from study design and
                                SAP development to sample size calculations and regulatory submission support.</p>
                            <a href="biostatistics" class="acc-btn">Learn More &rarr;</a>
                        </div>
                    </div>
                </div>

                <!-- Card 2 -->
                <div class="acc-service-card" style="background-image: url('assets/images/CPVIA_0004_Statistical Programming.webp');">
                    <div class="acc-overlay"></div>
                    <div class="acc-content">
                        <h3>Statistical<br>Programming</h3>
                        <h4>High-quality outputs</h4>
                        <div class="acc-hidden-content">
                            <p>High-quality SDTM, ADaM, TLFs, CSR outputs, and regulatory-ready deliverables. PK/PD
                                analysis, ad-hoc reports, and Pinnacle21 validation.</p>
                            <a href="statistical_programming" class="acc-btn">Learn More &rarr;</a>
                        </div>
                    </div>
                </div>

                <!-- Card 3 -->
                <div class="acc-service-card" style="background-image: url('assets/images/CPVIA_0003_CDISC.webp');">
                    <div class="acc-overlay"></div>
                    <div class="acc-content">
                        <h3>CDISC</h3>
                        <h4>End-to-end implementation</h4>
                        <div class="acc-hidden-content">
                            <p>CDISC implementation, validation, define.xml creation, SDRG/ADRG documentation, and
                                regulatory submission support aligned to FDA and EMA.</p>
                            <a href="services" class="acc-btn">Learn More &rarr;</a>
                        </div>
                    </div>
                </div>

                <!-- Card 4 -->
                <div class="acc-service-card" style="background-image: url('assets/images/CPVIA_0002_Cliinical Programming.webp');">
                    <div class="acc-overlay"></div>
                    <div class="acc-content">
                        <h3>Clinical<br>Programming</h3>
                        <h4>Scalable services</h4>
                        <div class="acc-hidden-content">
                            <p>Scalable clinical programming services including patient profiles, DM listings, edit
                                checks, reconciliation reports, and coding review across all areas.</p>
                            <a href="clinical_programming" class="acc-btn">Learn More &rarr;</a>
                        </div>
                    </div>
                </div>

                <!-- Card 5 -->
                <div class="acc-service-card" style="background-image: url('assets/images/CPVIA_0001_Visualization Analytics.webp');">
                    <div class="acc-overlay"></div>
                    <div class="acc-content">
                        <h3>Visualization<br>Analytics</h3>
                        <h4>Clinical intelligence</h4>
                        <div class="acc-hidden-content">
                            <p>Interactive Power BI & Spotfire dashboards, RBQM implementation, centralized monitoring
                                systems, and data visualization for real-time intelligence.</p>
                            <a href="services" class="acc-btn">Learn More &rarr;</a>
                        </div>
                    </div>
                </div>

                <!-- Card 6 -->
                <div class="acc-service-card" style="background-image: url('assets/images/CPVIA_0000_FSP Services.webp');">
                    <div class="acc-overlay"></div>
                    <div class="acc-content">
                        <h3>FSP Services</h3>
                        <h4>Dedicated resourcing</h4>
                        <div class="acc-hidden-content">
                            <p>Functional Service Provider model with dedicated biometrics and medical device resources,
                                scalable delivery teams, and capacity management.</p>
                            <a href="fsp" class="acc-btn">Learn More &rarr;</a>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <script>
            document.addEventListener("DOMContentLoaded", function () {
                const track = document.getElementById('services-carousel-track');
                const wrapper = document.querySelector('.services-carousel-wrapper');
                const cards = track.querySelectorAll('.acc-service-card');
                const prevBtn = document.getElementById('carousel-prev');
                const nextBtn = document.getElementById('carousel-next');

                let currentIndex = 0;
                const maxIndex = cards.length - 4; // 6 - 4 = 2 visible sliding steps
                let intervalId = null;
                const gap = 15; // gap in px

                function getSlideWidth() {
                    const wrapperWidth = wrapper.getBoundingClientRect().width;
                    const cardWidth = (wrapperWidth - (3 * gap)) / 4;
                    return cardWidth + gap;
                }

                function slideToIndex(index) {
                    const step = getSlideWidth();
                    track.style.transform = `translateX(-${index * step}px)`;
                    currentIndex = index;
                }

                function nextSlide() {
                    let nextIndex = currentIndex + 1;
                    if (nextIndex > maxIndex) {
                        nextIndex = 0;
                    }
                    slideToIndex(nextIndex);
                }

                function prevSlide() {
                    let prevIndex = currentIndex - 1;
                    if (prevIndex < 0) {
                        prevIndex = maxIndex;
                    }
                    slideToIndex(prevIndex);
                }

                function startAutoPlay() {
                    if (!intervalId) {
                        intervalId = setInterval(nextSlide, 3500); // auto slide every 3.5s
                    }
                }

                function stopAutoPlay() {
                    if (intervalId) {
                        clearInterval(intervalId);
                        intervalId = null;
                    }
                }

                startAutoPlay();

                wrapper.addEventListener('mouseenter', stopAutoPlay);
                wrapper.addEventListener('mouseleave', startAutoPlay);

                prevBtn.addEventListener('click', (e) => {
                    e.stopPropagation();
                    prevSlide();
                });

                nextBtn.addEventListener('click', (e) => {
                    e.stopPropagation();
                    nextSlide();
                });

                window.addEventListener('resize', () => {
                    slideToIndex(currentIndex);
                });
            });
        </script>
    </section>

    <!-- Gradient Divider -->
    <div class="gradient-divider"></div>

    <!-- Why Choose Us Section -->
    <section class="why-choose-section">
        <div class="section-badge orange"><span class="line orange-line"></span> WHY CHOOSE US</div>
        <h2 class="why-choose-heading">
            <span class="dark-text">The</span> <span class="highlight-purple underline-orange">CPVIA</span> <span
                class="highlight-purple">Advantage</span>
        </h2>
        <p class="section-desc">Built on deep domain expertise, innovation, and an unwavering<br>commitment to quality
            delivery for global clinical research.</p>

        <div class="advantages-grid">
            <div class="adv-card">
                <div class="adv-num">01</div>
                <div class="adv-icon purple-icon"><svg width="24" height="24" viewBox="0 0 24 24" fill="none"
                        stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <circle cx="12" cy="12" r="10"></circle>
                        <circle cx="12" cy="12" r="4"></circle>
                        <line x1="2" y1="12" x2="8" y2="12"></line>
                        <line x1="16" y1="12" x2="22" y2="12"></line>
                    </svg></div>
                <h3>Global Biometrics & Medical Device Expertise</h3>
                <p>Combined 175+ years of industry experience spanning Phase I–IV studies across pharma, biotech, CROs,
                    and medical device companies worldwide.</p>
            </div>
            <div class="adv-card">
                <div class="adv-num">02</div>
                <div class="adv-icon orange-icon"><svg width="24" height="24" viewBox="0 0 24 24" fill="none"
                        stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <polygon points="13 2 3 14 12 14 11 22 21 10 12 10 13 2"></polygon>
                    </svg></div>
                <h3>Innovation-Driven Delivery</h3>
                <p>Automation, AI-assisted workflows, and cutting-edge tooling that accelerate timelines while
                    maintaining regulatory quality standards.</p>
            </div>
            <div class="adv-card">
                <div class="adv-num">03</div>
                <div class="adv-icon purple-icon"><svg width="24" height="24" viewBox="0 0 24 24" fill="none"
                        stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <polyline points="9 11 12 14 22 4"></polyline>
                        <path d="M21 12v7a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11"></path>
                    </svg></div>
                <h3>100% Regulatory Success</h3>
                <p>Flawless FDA & EMA submission track record. Deep expertise in CDISC, define.xml, SDRG/ADRG, and
                    Pinnacle21 compliance validation.</p>
            </div>
            <div class="adv-card">
                <div class="adv-num">04</div>
                <div class="adv-icon orange-icon"><svg width="24" height="24" viewBox="0 0 24 24" fill="none"
                        stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"></path>
                        <circle cx="9" cy="7" r="4"></circle>
                        <path d="M23 21v-2a4 4 0 0 0-3-3.87"></path>
                        <path d="M16 3.13a4 4 0 0 1 0 7.75"></path>
                    </svg></div>
                <h3>Scalable Flex Teams</h3>
                <p>FSP engagement model with <10% attrition rate, rapid ramp-up, and dedicated biometrics & medical
                        device professionals aligned to your study needs.</p>
            </div>
            <div class="adv-card">
                <div class="adv-num">05</div>
                <div class="adv-icon purple-icon"><svg width="24" height="24" viewBox="0 0 24 24" fill="none"
                        stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <polyline points="22 12 18 12 15 21 9 3 6 12 2 12"></polyline>
                    </svg></div>
                <h3>One-Stop Biometrics & Medical Device Platform</h3>
                <p>From clinical data standards to advanced analytics, CPVIA unifies all biometrics and medical device
                    disciplines in a single, seamlessly integrated partnership model.</p>
            </div>
            <div class="adv-card">
                <div class="adv-num">06</div>
                <div class="adv-icon orange-icon"><svg width="24" height="24" viewBox="0 0 24 24" fill="none"
                        stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <circle cx="12" cy="12" r="10"></circle>
                        <line x1="2" y1="12" x2="22" y2="12"></line>
                        <path
                            d="M12 2a15.3 15.3 0 0 1 4 10 15.3 15.3 0 0 1-4 10 15.3 15.3 0 0 1-4-10 15.3 15.3 0 0 1 4-10z">
                        </path>
                    </svg></div>
                <h3>Global Delivery Network</h3>
                <p>Hyderabad-based COE with 24/7 delivery capability supporting US, UK, and European pharma and CRO
                    clients across multiple time zones.</p>
            </div>
        </div>
    </section>

    <!-- Domain Authority Section -->
    <section class="domain-authority-section">
        <div class="da-top-header">
            <div class="da-header-left">
                <div class="da-badge"><span class="da-line"></span> DOMAIN AUTHORITY</div>
                <h2 class="da-heading">
                    <span class="da-text-light">Our</span> <span class="da-text-orange">Expertise</span><br>
                    <span class="da-text-italic">in Clinical Biometrics & Medical Devices</span>
                </h2>
                <p class="da-desc">CPVIA delivers world-class biometrics and medical device consulting through deep
                    clinical domain authority, regulatory precision, and scalable enterprise delivery — from Phase I to
                    global submission.</p>
            </div>
            <div class="da-header-right">
                <div class="da-stat-box">
                    <div class="da-stat-value">100<span class="da-stat-accent">%</span></div>
                    <div class="da-stat-label">REGULATORY SUCCESS RATE</div>
                </div>
                <div class="da-stat-box">
                    <div class="da-stat-value">175<span class="da-stat-accent">+</span></div>
                    <div class="da-stat-label">YEARS COMBINED EXPERTISE</div>
                </div>
                <div class="da-stat-box">
                    <div class="da-stat-value">50<span class="da-stat-accent">+</span></div>
                    <div class="da-stat-label">GLOBAL STUDIES DELIVERED</div>
                </div>
            </div>
        </div>

        <div class="da-cards-grid">
            <!-- Card 1 -->
            <div class="da-card">
                <div class="da-card-icon-box purple-bg">
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="#a085ff" stroke-width="2"
                        stroke-linecap="round" stroke-linejoin="round">
                        <polyline points="22 12 18 12 15 21 9 3 6 12 2 12"></polyline>
                    </svg>
                </div>
                <div class="da-card-badge purple-text">CORE DOMAIN</div>
                <h3>Clinical Trial &<br>Medical Device Support</h3>
                <p>End-to-end biometrics and medical device support across Phase I-IV trials. Expert biostatisticians,
                    statistical programmers, and data managers working as one integrated unit from protocol to
                    regulatory package.</p>

                <div class="da-tags">
                    <span class="da-tag">Phase I-IV Coverage</span>
                    <span class="da-tag">SAP Development</span>
                    <span class="da-tag orange-tag">ISS / ISE</span>
                    <span class="da-tag">CSR Support</span>
                </div>

                <div class="da-card-footer">
                    <div class="da-footer-stat">
                        <div class="da-fstat-val orange-text">50+</div>
                        <div class="da-fstat-label">STUDIES</div>
                    </div>
                    <div class="da-footer-stat">
                        <div class="da-fstat-val orange-text">I-IV</div>
                        <div class="da-fstat-label">ALL PHASES</div>
                    </div>
                </div>
                <div class="da-card-bg-shape bg-shape-1"></div>
            </div>

            <!-- Card 2 -->
            <div class="da-card da-card-highlight">
                <div class="da-card-icon-box orange-bg">
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="#FF5500" stroke-width="2"
                        stroke-linecap="round" stroke-linejoin="round">
                        <polyline points="9 11 12 14 22 4"></polyline>
                        <path d="M21 12v7a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11"></path>
                    </svg>
                </div>
                <div class="da-card-badge orange-text">REGULATORY EXCELLENCE</div>
                <h3>Regulatory Submission<br>Expertise</h3>
                <p>Flawless NDA, BLA, and MAA submission packages. Deep mastery of FDA, EMA, and ICH standards with a
                    100% regulatory success track record across all submitted studies.</p>

                <div class="da-tags">
                    <span class="da-tag orange-tag">FDA / EMA</span>
                    <span class="da-tag orange-tag">NDA / BLA / MAA</span>
                    <span class="da-tag">Define.xml</span>
                    <span class="da-tag">SDRG / ADRG</span>
                </div>

                <div class="da-card-footer">
                    <div class="da-footer-stat">
                        <div class="da-fstat-val orange-text">100%</div>
                        <div class="da-fstat-label">SUCCESS RATE</div>
                    </div>
                    <div class="da-footer-stat">
                        <div class="da-fstat-val orange-text">3+</div>
                        <div class="da-fstat-label">AGENCIES</div>
                    </div>
                </div>
                <div class="da-card-bg-shape bg-shape-2"></div>
            </div>

            <!-- Card 3 -->
            <div class="da-card">
                <div class="da-card-icon-box purple-bg">
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="#a085ff" stroke-width="2"
                        stroke-linecap="round" stroke-linejoin="round">
                        <polygon points="12 2 2 7 12 12 22 7 12 2"></polygon>
                        <polyline points="2 12 12 17 22 12"></polyline>
                        <polyline points="2 17 12 22 22 17"></polyline>
                    </svg>
                </div>
                <div class="da-card-badge purple-text">DATA STANDARDS</div>
                <h3>CDISC-Compliant<br>Clinical Data Standards</h3>
                <p>Rigorous SDTM and ADaM implementation with Pinnacle21 validation. CDISC-certified expertise ensuring
                    data packages meet the highest regulatory and operational standards globally.</p>

                <div class="da-tags">
                    <span class="da-tag">SDTM Implementation</span>
                    <span class="da-tag">ADaM Datasets</span>
                    <span class="da-tag orange-tag">Pinnacle21</span>
                    <span class="da-tag">Data Review</span>
                </div>

                <div class="da-card-footer">
                    <div class="da-footer-stat">
                        <div class="da-fstat-val orange-text">98%</div>
                        <div class="da-fstat-label">SDTM COMPLIANCE</div>
                    </div>
                    <div class="da-footer-stat">
                        <div class="da-fstat-val orange-text">0</div>
                        <div class="da-fstat-label">CRITICAL ERRORS</div>
                    </div>
                </div>
                <div class="da-card-bg-shape bg-shape-3"></div>
            </div>
        </div>

        <div class="da-workflow-section">
            <div class="da-orange-glow"></div>
            <h3 class="da-workflow-title"><span class="da-text-orange">End-to-End</span> <span
                    class="da-text-italic">Clinical Trial Delivery Workflow</span></h3>

            <div class="da-workflow-timeline">
                <div class="da-step">
                    <div class="da-step-icon">
                        <div class="da-step-num">1</div>
                        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                            stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"></path>
                            <polyline points="14 2 14 8 20 8"></polyline>
                            <line x1="16" y1="13" x2="8" y2="13"></line>
                            <line x1="16" y1="17" x2="8" y2="17"></line>
                            <polyline points="10 9 9 9 8 9"></polyline>
                        </svg>
                    </div>
                    <div class="da-step-content">
                        <h4>Protocol &<br>SAP Review</h4>
                        <p>Study Design</p>
                    </div>
                </div>
                <div class="da-step-connector"></div>
                <div class="da-step">
                    <div class="da-step-icon">
                        <div class="da-step-num">2</div>
                        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                            stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <ellipse cx="12" cy="5" rx="9" ry="3"></ellipse>
                            <path d="M21 12c0 1.66-4 3-9 3s-9-1.34-9-3"></path>
                            <path d="M3 5v14c0 1.66 4 3 9 3s9-1.34 9-3V5"></path>
                        </svg>
                    </div>
                    <div class="da-step-content">
                        <h4>CDISC Data<br>Standards Setup</h4>
                        <p>SDTM / ADaM</p>
                    </div>
                </div>
                <div class="da-step-connector"></div>
                <div class="da-step">
                    <div class="da-step-icon">
                        <div class="da-step-num">3</div>
                        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                            stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <polyline points="16 18 22 12 16 6"></polyline>
                            <polyline points="8 6 2 12 8 18"></polyline>
                        </svg>
                    </div>
                    <div class="da-step-content">
                        <h4>Statistical<br>Programming</h4>
                        <p>TLFs &middot; PK/PD</p>
                    </div>
                </div>
                <div class="da-step-connector"></div>
                <div class="da-step">
                    <div class="da-step-icon">
                        <div class="da-step-num">4</div>
                        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                            stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <polyline points="22 12 18 12 15 21 9 3 6 12 2 12"></polyline>
                        </svg>
                    </div>
                    <div class="da-step-content">
                        <h4>Safety & Efficacy<br>Analysis</h4>
                        <p>ISS &middot; ISE &middot; CSR</p>
                    </div>
                </div>
                <div class="da-step-connector"></div>
                <div class="da-step">
                    <div class="da-step-icon">
                        <div class="da-step-num">5</div>
                        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                            stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"></path>
                            <circle cx="12" cy="12" r="3"></circle>
                        </svg>
                    </div>
                    <div class="da-step-content">
                        <h4>QC & RBQM<br>Validation</h4>
                        <p>Pinnacle21 &middot; P21</p>
                    </div>
                </div>
                <div class="da-step-connector"></div>
                <div class="da-step">
                    <div class="da-step-icon">
                        <div class="da-step-num">6</div>
                        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                            stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <polyline points="9 11 12 14 22 4"></polyline>
                            <path d="M21 12v7a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11"></path>
                        </svg>
                    </div>
                    <div class="da-step-content">
                        <h4>Regulatory<br>Submission</h4>
                        <p>FDA &middot; EMA &middot; ICH</p>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Gradient Divider -->
    <div class="gradient-divider"></div>
    <!-- our partners Section -->
    <!-- Testimonials Section -->
    <section class="testimonials-section">
        <div class="testi-header">
            <div class="testi-badge"><span class="testi-line"></span> CLIENT TESTIMONIALS</div>
            <h2 class="testi-heading">What Our <span class="testi-highlight">Partners</span> Say</h2>
        </div>

        <div class="carousel-container">
            <div class="carousel-track" id="testimonial-track">

                <!-- Slide 1 -->
                <div class="testimonial-card-new">
                    <div class="testi-quote-icon">"</div>
                    <p class="testi-quote-text">The CPVIA team's depth in CDISC standardization and define.xml
                        validation was impressive. Their FSP model gave us exactly the flexibility we needed to scale
                        resources during our Phase III clinical program.</p>
                    <div class="testi-author">
                        <div class="testi-avatar">DT</div>
                        <div class="testi-author-info">
                            <strong>David Thompson</strong>
                            <span>VP Clinical Operations &mdash; UK Pharmaceutical Company</span>
                            <div class="testi-stars">
                                <svg width="12" height="12" viewBox="0 0 24 24" fill="#FF5500"
                                    xmlns="http://www.w3.org/2000/svg">
                                    <path
                                        d="M12 2L15.09 8.26L22 9.27L17 14.14L18.18 21.02L12 17.77L5.82 21.02L7 14.14L2 9.27L8.91 8.26L12 2Z" />
                                </svg>
                                <svg width="12" height="12" viewBox="0 0 24 24" fill="#FF5500"
                                    xmlns="http://www.w3.org/2000/svg">
                                    <path
                                        d="M12 2L15.09 8.26L22 9.27L17 14.14L18.18 21.02L12 17.77L5.82 21.02L7 14.14L2 9.27L8.91 8.26L12 2Z" />
                                </svg>
                                <svg width="12" height="12" viewBox="0 0 24 24" fill="#FF5500"
                                    xmlns="http://www.w3.org/2000/svg">
                                    <path
                                        d="M12 2L15.09 8.26L22 9.27L17 14.14L18.18 21.02L12 17.77L5.82 21.02L7 14.14L2 9.27L8.91 8.26L12 2Z" />
                                </svg>
                                <svg width="12" height="12" viewBox="0 0 24 24" fill="#FF5500"
                                    xmlns="http://www.w3.org/2000/svg">
                                    <path
                                        d="M12 2L15.09 8.26L22 9.27L17 14.14L18.18 21.02L12 17.77L5.82 21.02L7 14.14L2 9.27L8.91 8.26L12 2Z" />
                                </svg>
                                <svg width="12" height="12" viewBox="0 0 24 24" fill="#FF5500"
                                    xmlns="http://www.w3.org/2000/svg">
                                    <path
                                        d="M12 2L15.09 8.26L22 9.27L17 14.14L18.18 21.02L12 17.77L5.82 21.02L7 14.14L2 9.27L8.91 8.26L12 2Z" />
                                </svg>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Slide 2 -->
                <div class="testimonial-card-new">
                    <div class="testi-quote-icon">"</div>
                    <p class="testi-quote-text">Working with CPVIA's biometrics & medical device team fundamentally
                        accelerated our regulatory timeline. The attention to detail in SAP development and ADaM
                        datasets was outstanding and completely audit-ready.</p>
                    <div class="testi-author">
                        <div class="testi-avatar">SJ</div>
                        <div class="testi-author-info">
                            <strong>Sarah Jenkins</strong>
                            <span>Director of Biometrics & Medical Devices &mdash; Global Biotech</span>
                            <div class="testi-stars">
                                <svg width="12" height="12" viewBox="0 0 24 24" fill="#FF5500"
                                    xmlns="http://www.w3.org/2000/svg">
                                    <path
                                        d="M12 2L15.09 8.26L22 9.27L17 14.14L18.18 21.02L12 17.77L5.82 21.02L7 14.14L2 9.27L8.91 8.26L12 2Z" />
                                </svg>
                                <svg width="12" height="12" viewBox="0 0 24 24" fill="#FF5500"
                                    xmlns="http://www.w3.org/2000/svg">
                                    <path
                                        d="M12 2L15.09 8.26L22 9.27L17 14.14L18.18 21.02L12 17.77L5.82 21.02L7 14.14L2 9.27L8.91 8.26L12 2Z" />
                                </svg>
                                <svg width="12" height="12" viewBox="0 0 24 24" fill="#FF5500"
                                    xmlns="http://www.w3.org/2000/svg">
                                    <path
                                        d="M12 2L15.09 8.26L22 9.27L17 14.14L18.18 21.02L12 17.77L5.82 21.02L7 14.14L2 9.27L8.91 8.26L12 2Z" />
                                </svg>
                                <svg width="12" height="12" viewBox="0 0 24 24" fill="#FF5500"
                                    xmlns="http://www.w3.org/2000/svg">
                                    <path
                                        d="M12 2L15.09 8.26L22 9.27L17 14.14L18.18 21.02L12 17.77L5.82 21.02L7 14.14L2 9.27L8.91 8.26L12 2Z" />
                                </svg>
                                <svg width="12" height="12" viewBox="0 0 24 24" fill="#FF5500"
                                    xmlns="http://www.w3.org/2000/svg">
                                    <path
                                        d="M12 2L15.09 8.26L22 9.27L17 14.14L18.18 21.02L12 17.77L5.82 21.02L7 14.14L2 9.27L8.91 8.26L12 2Z" />
                                </svg>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Slide 3 -->
                <div class="testimonial-card-new">
                    <div class="testi-quote-icon">"</div>
                    <p class="testi-quote-text">The level of engineering validation and Part 11 compliance support we
                        received was top tier. CPVIA integrated perfectly with our internal teams to deliver the medical
                        device software on schedule.</p>
                    <div class="testi-author">
                        <div class="testi-avatar">MR</div>
                        <div class="testi-author-info">
                            <strong>Michael Reynolds</strong>
                            <span>Head of R&D &mdash; US Medical Devices</span>
                            <div class="testi-stars">
                                <svg width="12" height="12" viewBox="0 0 24 24" fill="#FF5500"
                                    xmlns="http://www.w3.org/2000/svg">
                                    <path
                                        d="M12 2L15.09 8.26L22 9.27L17 14.14L18.18 21.02L12 17.77L5.82 21.02L7 14.14L2 9.27L8.91 8.26L12 2Z" />
                                </svg>
                                <svg width="12" height="12" viewBox="0 0 24 24" fill="#FF5500"
                                    xmlns="http://www.w3.org/2000/svg">
                                    <path
                                        d="M12 2L15.09 8.26L22 9.27L17 14.14L18.18 21.02L12 17.77L5.82 21.02L7 14.14L2 9.27L8.91 8.26L12 2Z" />
                                </svg>
                                <svg width="12" height="12" viewBox="0 0 24 24" fill="#FF5500"
                                    xmlns="http://www.w3.org/2000/svg">
                                    <path
                                        d="M12 2L15.09 8.26L22 9.27L17 14.14L18.18 21.02L12 17.77L5.82 21.02L7 14.14L2 9.27L8.91 8.26L12 2Z" />
                                </svg>
                                <svg width="12" height="12" viewBox="0 0 24 24" fill="#FF5500"
                                    xmlns="http://www.w3.org/2000/svg">
                                    <path
                                        d="M12 2L15.09 8.26L22 9.27L17 14.14L18.18 21.02L12 17.77L5.82 21.02L7 14.14L2 9.27L8.91 8.26L12 2Z" />
                                </svg>
                                <svg width="12" height="12" viewBox="0 0 24 24" fill="#FF5500"
                                    xmlns="http://www.w3.org/2000/svg">
                                    <path
                                        d="M12 2L15.09 8.26L22 9.27L17 14.14L18.18 21.02L12 17.77L5.82 21.02L7 14.14L2 9.27L8.91 8.26L12 2Z" />
                                </svg>
                            </div>
                        </div>
                    </div>
                </div>

            </div>
        </div>

        <div class="carousel-dots" id="testimonial-dots">
            <span class="carousel-dot active" data-slide="0"></span>
            <span class="carousel-dot" data-slide="1"></span>
            <span class="carousel-dot" data-slide="2"></span>
        </div>
    </section>

    <script>
        document.addEventListener("DOMContentLoaded", function () {
            const track = document.getElementById('testimonial-track');
            const dots = document.querySelectorAll('.carousel-dot');
            let currentIndex = 0;
            const totalSlides = dots.length;

            function updateCarousel(index) {
                track.style.transform = 'translateX(-' + (index * 100) + '%)';
                dots.forEach(d => d.classList.remove('active'));
                dots[index].classList.add('active');
                currentIndex = index;
            }

            dots.forEach((dot, index) => {
                dot.addEventListener('click', () => {
                    updateCarousel(index);
                });
            });

            // Auto transition every 6 seconds
            setInterval(() => {
                let nextIndex = (currentIndex + 1) % totalSlides;
                updateCarousel(nextIndex);
            }, 6000);
        });
    </script>

    <!-- global Partners Section -->
    <section class="partners-section">
        <div class="partners-badge-wrapper">
            <span class="line orange-line short"></span> <span class="partners-badge-text">GLOBAL PARTNERS</span> <span
                class="line orange-line short"></span>
        </div>
        <div class="partners-subtitle">TRUSTED BY GLOBAL PHARMA & CRO PARTNERS</div>
        <div class="partners-underline"></div>

        <div class="partners-marquee-container">
            <div class="partners-marquee-track">
                <div class="partner-pill">US Pharma Partners</div>
                <div class="partner-pill">UK CRO Leaders</div>
                <div class="partner-pill">Global Biotech</div>
                <div class="partner-pill">Medical Devices</div>
                <div class="partner-pill">Phase I-IV Studies</div>
                <div class="partner-pill">FDA Submissions</div>
                <div class="partner-pill">EMA Compliance</div>
                <!-- Duplicates for seamless scrolling -->
                <div class="partner-pill">US Pharma Partners</div>
                <div class="partner-pill">UK CRO Leaders</div>
                <div class="partner-pill">Global Biotech</div>
                <div class="partner-pill">Medical Devices</div>
                <div class="partner-pill">Phase I-IV Studies</div>
                <div class="partner-pill">FDA Submissions</div>
                <div class="partner-pill">EMA Compliance</div>
            </div>
        </div>
    </section>

    <!-- CTA Section -->
    <section class="cta-section" style="background-image: url('assets/images/partner-with-cpvia.jpg');">
        <div class="cta-overlay"></div>
        <div class="cta-orange-glow"></div>
        <div class="cta-content">
            <div class="partners-badge-wrapper cta-badge">
                <span class="line orange-line short"></span> <span class="partners-badge-text">PARTNER WITH CPVIA</span>
                <span class="line orange-line short"></span>
            </div>
            <h2>Looking For A <span class="cta-italic-orange">Reliable</span><br>Clinical Research Partner?</h2>

            <a href="contact" class="btn cta-btn">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"
                    stroke-linecap="round" stroke-linejoin="round">
                    <path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z"></path>
                    <polyline points="22,6 12,13 2,6"></polyline>
                </svg>
                CONTACT OUR TEAM
            </a>
        </div>
    </section>

    <!-- Footer -->
    <?php include 'footer.php'; ?>
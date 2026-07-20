<?php include 'header.php'; ?>
<main>
    <!-- Contact Hero -->
    <section class="hero contact-hero">
        <div class="hero-content">
            <div class="subtitle contact-subtitle">
                <span class="dot"></span> CONNECT WITH CPVIA
            </div>
            <h1>Let's Build Your<br>Next Clinical<br><span class="hero-serif"
                    style="color:var(--primary-orange);">Research Partnership</span></h1>
            <p>Reach our expert biometrics & medical device team for project scoping, consultation, or career inquiries. We respond
                within 24 hours.</p>
            <div class="contact-badges">
                <span class="c-badge">📞 24hr Response</span>
                <span class="c-badge">📄 Free Consultation</span>
                <span class="c-badge">🔒 100% Confidential</span>
            </div>
        </div>
        <img src="assets/images/contact-us.png" alt="Clinical Professional" class="hero-img contact-hero-img">
    </section>

    <!-- Contact Methods -->
    <section class="contact-methods-section" style="text-align: center;">
        <div class="section-badge orange" style="justify-content: center; margin: 0 auto 1.5rem;">
            <span class="line orange-line"></span> REACH US DIRECTLY <span class="line orange-line"></span>
        </div>
        <h2
            style="text-align: center; font-size: 2.8rem; font-family: var(--font-sans); font-weight:800; line-height: normal; color:var(--primary-blue); margin-bottom: 1rem;">
            Multiple Ways to Connect<br>With Our <span
                style="font-family:var(--font-serif); font-style:italic; color:var(--primary-orange); font-weight:600;">Expert
                Team</span></h2>
        <p
            style="text-align: center; max-width: 600px; margin: 0 auto 4rem; color:var(--text-light); font-size:1.05rem;">
            Whether you prefer email, a quick call, or WhatsApp — we're available across all channels with dedicated
            response protocols.</p>

        <div class="methods-grid">
            <div class="method-card" style="border-top-color: var(--primary-blue);">
                <div class="m-icon" style="color: var(--primary-blue); background: #F4F2FF;">✉</div>
                <div class="m-label">EMAIL ADDRESS</div>
                <div class="m-value">info@cpvia.com</div>
                <p>For project inquiries, proposals, and partnership discussions.</p>
                <a href="mailto:info@cpvia.com" class="m-link" style="color: var(--primary-blue);">Send Email &rarr;</a>
            </div>
            <div class="method-card" style="border-top-color: var(--primary-orange);">
                <div class="m-icon" style="color: var(--primary-orange); background: #FFF3EE;">📞</div>
                <div class="m-label">PHONE NUMBER</div>
                <div class="m-value">+91 97041 21620</div>
                <p>Direct line to our consulting team. Available Mon-Fri, 9AM-7PM IST.</p>
                <a href="tel:+919704121620" class="m-link" style="color: var(--primary-orange);">Call Now &rarr;</a>
            </div>
            <div class="method-card" style="border-top-color: #22C55E;">
                <div class="m-icon" style="color: #22C55E; background: #Edfdf4;">💬</div>
                <div class="m-label">WHATSAPP</div>
                <div class="m-value">+91 97041 21620</div>
                <p>Quick queries and document sharing via WhatsApp Business.</p>
                <a href="#" class="m-link" style="color: #22C55E;">Message Us &rarr;</a>
            </div>
            <div class="method-card" style="border-top-color: #0891B2;">
                <div class="m-icon" style="color: #0891B2; background: #ecfeff;">📍</div>
                <div class="m-label">OFFICE LOCATION</div>
                <div class="m-value">Hyderabad, India</div>
                <p>Telangana, India — the pharma & life sciences hub of South Asia.</p>
                <a href="#" class="m-link" style="color: #0891B2;">View Map &rarr;</a>
            </div>
        </div>
    </section>

    <!-- Form Section -->
    <section class="form-section">
        <div class="form-container">
            <div class="form-text-col">
                <div class="section-badge orange"><span class="line orange-line"></span> SEND AN INQUIRY</div>
                <h2 style="font-size: 2.8rem; line-height:1.2; margin-bottom: 1.5rem;">Talk to Our <span
                        style="color:var(--primary-blue); font-family:var(--font-sans); font-weight:800;">Biometrics</span><br><span
                        class="hero-serif" style="color:var(--primary-orange);">Specialists</span></h2>
                <p style="color:var(--text-light); max-width:400px; margin-bottom: 2rem; font-size:1.05rem;">Fill in the
                    form and our team will get back to you within one business day with a tailored response.</p>
            </div>

            <div class="form-box-col">
                <div class="form-box">
                    <h3>Send Us a Message</h3>
                    <p class="form-req">All fields marked * are required. We'll respond within 24 hours.</p>

                    <form action="send-mail.php" method="POST" class="contact-form">
                        <div class="form-row">
                            <div class="input-group">
                                <label>FIRST NAME *</label>
                                <input type="text" placeholder="e.g. Ravi" required>
                            </div>
                            <div class="input-group">
                                <label>LAST NAME *</label>
                                <input type="text" placeholder="e.g. Sharma" required>
                            </div>
                        </div>
                        <div class="form-row">
                            <div class="input-group">
                                <label>WORK EMAIL *</label>
                                <input type="email" placeholder="name@company.com" required>
                            </div>
                            <div class="input-group">
                                <label>PHONE NUMBER</label>
                                <input type="tel" placeholder="+91 00000 00000">
                            </div>
                        </div>
                        <div class="form-row">
                            <div class="input-group">
                                <label>COMPANY / ORGANISATION</label>
                                <input type="text" placeholder="Your company name">
                            </div>
                            <div class="input-group">
                                <label>COUNTRY</label>
                                <input type="text" placeholder="e.g. India, USA, UK">
                            </div>
                        </div>
                        <div class="input-group full">
                            <label>AREA OF INTEREST *</label>
                            <select required>
                                <option value="" disabled selected>Select a service area</option>
                                <option value="Biostatistics">Biostatistics</option>
                                <option value="Programming">Statistical Programming</option>
                                <option value="CDISC">CDISC Services</option>
                                <option value="Analytics">Visualization & Analytics</option>
                                <option value="FSP">FSP Resourcing</option>
                            </select>
                        </div>
                        <div class="input-group full">
                            <label>MESSAGE *</label>
                            <textarea rows="4"
                                placeholder="Tell us about your project, timeline, and any specific requirements..."
                                required></textarea>
                        </div>
                        <div class="form-footer">
                            <p class="privacy-text">By submitting this form, you agree to our <strong>Privacy
                                    Policy</strong> and consent to CPVIA contacting you about our services.</p>
                            <button type="submit" class="btn btn-primary">SEND MESSAGE</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </section>

    <!-- Why CPVIA Stats Section -->
    <section class="contact-stats-section">
        <div class="c-stats-container">
            <div class="c-stats-text">
                <div class="section-badge"><span class="line"></span> WHY CPVIA <span class="line"></span></div>
                <h2
                    style="font-size: 2.8rem; font-family: var(--font-sans); font-weight:800; line-height:1; margin-bottom: 1.5rem; color:#111;">
                    A Team That Speaks<br>Your <span style="color: rgba(42, 0, 124, 1);">Clinical Language</span></h2>
                <p class="c-stats-quote">"Precision-driven biometrics partnerships built on trust, compliance, and
                    innovation."</p>
                <p class="c-stats-desc">From first contact to regulatory submission, CPVIA delivers end-to-end clinical
                    programming expertise with the agility of a specialist partner and the rigour of a global CRO.</p>
                <div class="c-stats-tags">
                    <span class="c-tag"><span class="icon">✅</span> FDA &amp; EMA Compliant</span>
                    <span class="c-tag"><span class="icon">⚡</span> 24hr Turnaround</span>
                    <span class="c-tag"><span class="icon">🌐</span> Global CRO Network</span>
                </div>
            </div>

            <div class="c-stats-grid">
                <div class="c-stat-box">
                    <h3 style="color:var(--primary-blue);">15+</h3>
                    <p>Years Combined<br>Experience</p>
                </div>
                <div class="c-stat-box">
                    <h3 style="color:var(--primary-orange);">200+</h3>
                    <p>Studies Delivered</p>
                </div>
                <div class="c-stat-box">
                    <h3 style="color:#22C55E;">98%</h3>
                    <p>Client Retention Rate</p>
                </div>
                <div class="c-stat-box">
                    <h3 style="color:#0891B2;">24hr</h3>
                    <p>Guaranteed Response<br>Time</p>
                </div>
            </div>
        </div>
    </section>
    <!-- Map Section -->
    <section class="map-section" style="text-align: center; padding:50px 0;">
        <div class="section-badge orange"><span class="line orange-line"></span> OUR LOCATION <span
                class="line orange-line"></span></div>
        <h2
            style="text-align: center; font-size: 2.8rem; font-family: var(--font-sans); font-weight: 800; color: #111; line-height: 1.2;">
            "Based in Hyderabad, the<br>heart of <span style="color:var(--primary-blue);">India's Pharma Hub</span>"
        </h2>
        <p
            style="text-align: center; color: var(--text-light); margin: 1.5rem auto 4rem; max-width: 600px; font-weight: 500; font-size:1.05rem;">
            Strategically located in Hyderabad, Telangana — India's premier pharmaceutical and life sciences corridor.
        </p>

        <div class="map-container"
            style="max-width: 1100px; margin: 0 auto; border-radius: 20px; overflow: hidden; box-shadow: 0 10px 40px rgba(0,0,0,0.1);">
            <iframe
                src="https://www.google.com/maps/embed?pb=!1m18!1m12!1m3!1d3807.2491659225902!2d78.55296057331803!3d17.399826102450323!2m3!1f0!2f0!3f0!3m2!1i1024!2i768!4f13.1!3m3!1m2!1s0x3bcb98e539d7f62f%3A0x135b355a3dda97fc!2sCPVIA%20Private%20Limited!5e0!3m2!1sen!2sin!4v1783402698206!5m2!1sen!2sin"
                width="100%" height="450" style="border:0;" allowfullscreen="" loading="lazy"
                referrerpolicy="strict-origin-when-cross-origin"></iframe>
        </div>
    </section>

    <!-- Alternate CTA Section for Contact Page -->
    <section class="cta-section">
        <div class="section-badge orange"><span class="line orange-line"></span> PARTNER WITH CPVIA <span
                class="line orange-line"></span></div>
        <h2 style="line-height: normal;">Ready to Elevate Your<br><span class="highlight-orange">Clinical
                Research</span> Program?</h2>
        <p>Partner with CPVIA for scalable, compliant, and innovation-driven biometrics and medical device solutions. From Phase I to
            regulatory submission — we deliver excellence at every stage.</p>
        <a href="#" class="btn btn-outline-white"><span class="icon">💬</span> WHATSAPP US NOW</a>
    </section>
</main>
<?php include 'footer.php'; ?>
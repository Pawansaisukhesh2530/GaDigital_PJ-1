            </main>
        </div>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', function () {
            const hamburger = document.getElementById('adminHamburger');
            const sidebar = document.getElementById('adminSidebar');
            const overlay = document.getElementById('adminSidebarOverlay');

            function closeSidebar() {
                sidebar.classList.remove('open');
                overlay.classList.remove('open');
            }

            if (hamburger && sidebar && overlay) {
                hamburger.addEventListener('click', function () {
                    sidebar.classList.toggle('open');
                    overlay.classList.toggle('open');
                });
                overlay.addEventListener('click', closeSidebar);
            }
        });
    </script>
</body>
</html>

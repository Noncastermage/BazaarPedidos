</main>
    </div>

    <script>
        // Menú móvil
        document.addEventListener('DOMContentLoaded', function() {
            const menuToggle = document.querySelector('.mobile-menu-toggle');
            const mainNav = document.getElementById('main-nav');
            
            if (menuToggle) {
                menuToggle.addEventListener('click', function() {
                    mainNav.classList.toggle('active');
                });
            }
            
            // Hacer que las tablas sean responsivas
            const tables = document.querySelectorAll('table:not(.no-responsive)');
            tables.forEach(function(table) {
                const headerCells = table.querySelectorAll('thead th');
                const headerTexts = Array.from(headerCells).map(cell => cell.textContent.trim());
                
                const bodyRows = table.querySelectorAll('tbody tr');
                bodyRows.forEach(function(row) {
                    const cells = row.querySelectorAll('td');
                    cells.forEach(function(cell, index) {
                        if (index < headerTexts.length) {
                            cell.setAttribute('data-label', headerTexts[index]);
                        }
                    });
                });
            });
        });
    </script>
    
    <?php if (isset($extra_js)): ?>
    <script>
        <?= $extra_js ?>
    </script>
    <?php endif; ?>
</body>
</html>


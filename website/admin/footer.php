
           </div>
          </div>
        </main>
      </div>
    </div>

    <!--script src="https://cdn.jsdelivr.net/npm/@popperjs/core@2.9.2/dist/umd/popper.min.js" crossorigin="anonymous"></script-->
    
    <script src="assets/bootstrap/bootstrap.bundle.min.js" crossorigin="anonymous"></script>

    <script src="assets/bootstrap/feather.min.js" crossorigin="anonymous"></script>
    
    <script>
    function enableTooltips() {
        var tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'))
        var tooltipList = tooltipTriggerList.map(function (tooltipTriggerEl) {
          return new bootstrap.Tooltip(tooltipTriggerEl);
        });
    }
    document.addEventListener('DOMContentLoaded', enableTooltips());
    </script>
    
  </body>
</html>

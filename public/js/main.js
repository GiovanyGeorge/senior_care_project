document.addEventListener('DOMContentLoaded', function () {
    var categorySelect = document.getElementById('category_id') || document.getElementById('service_category_id');
    var costDisplay = document.getElementById('points-cost-value') || document.getElementById('points-cost');

    if (!categorySelect || !costDisplay) {
        return;
    }

    categorySelect.addEventListener('change', function () {
        var selectedOption = categorySelect.options[categorySelect.selectedIndex];
        var cost = selectedOption ? selectedOption.getAttribute('data-cost') : null;

        if (cost !== null && cost !== '') {
            costDisplay.textContent = cost + ' SilverPoints';
            return;
        }

        var categoryId = categorySelect.value;
        if (!categoryId) {
            costDisplay.textContent = '0 SilverPoints';
            return;
        }

        fetch('/senior_care/controllers/VisitController.php?action=getPointsCost&category_id=' + encodeURIComponent(categoryId))
            .then(function (response) { return response.json(); })
            .then(function (data) {
                costDisplay.textContent = (data.cost || 0) + ' SilverPoints';
            })
            .catch(function () {
                costDisplay.textContent = 'Unable to load cost';
            });
    });
});

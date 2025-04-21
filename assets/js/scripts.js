document.addEventListener('DOMContentLoaded', function () {
    // Tabs functionality
    const tabs = document.querySelectorAll('.tab');
    const tabContents = document.querySelectorAll('.tab-content');

    tabs.forEach(tab => {
        tab.addEventListener('click', function () {
            const day = this.getAttribute('data-day');
            tabs.forEach(t => t.classList.remove('active'));
            this.classList.add('active');
            tabContents.forEach(content => content.classList.remove('active'));
            document.getElementById(`content-${day}`).classList.add('active');
        });
    });

    // Success message fade-out
    const successMessage = document.getElementById('successMessage');
    if (successMessage) {
        setTimeout(() => {
            successMessage.classList.add('fade-out');
            setTimeout(() => {
                successMessage.remove();
            }, 500);
        }, 3000);
    }

    // User Search
    const userSearch = document.getElementById('userSearch');
    const userDropdown = document.getElementById('userDropdown');
    const userActions = document.getElementById('userActions');
    const selectedUserId = document.getElementById('selectedUserId');
    const cancelUserId = document.getElementById('cancelUserId');
    const friendsFamilyUserId = document.getElementById('friendsFamilyUserId');
    const editUserLink = document.getElementById('editUserLink');
    const viewWorkoutsLink = document.getElementById('viewWorkoutsLink');

    const users = window.users || [];

    if (userSearch) {
        userSearch.addEventListener('input', function () {
            const query = this.value.toLowerCase();
            userDropdown.innerHTML = '';
            if (query.length > 0) {
                const filteredUsers = users.filter(user => user.email.toLowerCase().startsWith(query));
                filteredUsers.forEach(user => {
                    const div = document.createElement('div');
                    div.textContent = `${user.email} (${user.username})`;
                    div.dataset.id = user.id;
                    div.addEventListener('click', function () {
                        selectedUserId.value = user.id;
                        cancelUserId.value = user.id;
                        friendsFamilyUserId.value = user.id;
                        userSearch.value = user.email;
                        editUserLink.href = `/edit_user?id=${user.id}`;
                        viewWorkoutsLink.href = `/dash?user_id=${user.id}`;
                        userActions.style.display = 'block';
                        userDropdown.style.display = 'none';
                    });
                    userDropdown.appendChild(div);
                });
                userDropdown.style.display = filteredUsers.length > 0 ? 'block' : 'none';
            } else {
                userDropdown.style.display = 'none';
                userActions.style.display = 'none';
            }
        });

        document.addEventListener('click', function (e) {
            if (!userSearch.contains(e.target) && !userDropdown.contains(e.target)) {
                userDropdown.style.display = 'none';
            }
        });
    }

    // Add/Remove Working Sets
    let setCount = 1;

    window.addWorkingSet = function (button = null, workoutId = null) {
        const container = button ? button.parentElement.querySelector('.working-sets') : document.getElementById('prescribeWorkingSets');
        const index = button ? container.querySelectorAll('.working-set').length : setCount;
        const unit = document.querySelector('input[name="unit_preference"]')?.value || 'kg';
        const setDiv = document.createElement('div');
        setDiv.className = 'working-set';
        setDiv.innerHTML = `
            <input type="number" name="sets[${index}][reps]" placeholder="Reps" min="1" required>
            <input type="number" name="sets[${index}][weight]" placeholder="${unit}" min="0" step="0.01">
            <button type="button" class="remove-set" onclick="removeWorkingSet(this)">Remove</button>
        `;
        container.appendChild(setDiv);
        if (!button) setCount++;
    };

    window.removeWorkingSet = function (button) {
        button.parentElement.remove();
        const container = button.closest('.working-sets');
        const sets = container.querySelectorAll('.working-set');
        sets.forEach((set, index) => {
            set.querySelectorAll('input').forEach(input => {
                const name = input.name.replace(/\[\d+\]/, `[${index}]`);
                input.name = name;
            });
        });
        setCount = sets.length;
    };

    // Weight Chart
    const weightCtx = document.getElementById('weightChart')?.getContext('2d');
    if (weightCtx) {
        const weightChart = new Chart(weightCtx, {
            type: 'line',
            data: {
                labels: [],
                datasets: [{
                    label: 'Weight',
                    data: [],
                    borderColor: '#1a73e8',
                    borderWidth: 2,
                    pointBackgroundColor: '#1a73e8',
                    pointBorderColor: '#ffffff',
                    pointBorderWidth: 2,
                    pointRadius: 4,
                    fill: false,
                    tension: 0.4
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                scales: {
                    y: {
                        beginAtZero: false,
                        grid: { color: 'rgba(122, 122, 122, 0.1)', drawBorder: false },
                        ticks: { color: '#1a1a1a', font: { family: 'Inter', size: 10 } }
                    },
                    x: {
                        grid: { display: false },
                        ticks: { color: '#1a1a1a', font: { family: 'Inter', size: 10 } }
                    }
                }
            }
        });

        // Update Weight Chart with data from PHP
        if (window.weightData) {
            weightChart.data.labels = window.weightData.labels;
            weightChart.data.datasets[0].data = window.weightData.weights;
            weightChart.data.datasets[0].label = `Weight (${window.unitPreference || 'kg'})`;
            weightChart.update();
        }
    }

    // Macros Chart
    const macrosCtx = document.getElementById('macrosChart')?.getContext('2d');
    if (macrosCtx) {
        const macrosChart = new Chart(macrosCtx, {
            type: 'bar',
            data: {
                labels: ['Protein', 'Carbs', 'Fats'],
                datasets: [{
                    label: 'Macros (g)',
                    data: [],
                    backgroundColor: ['#34c759', '#1a73e8', '#e63946'],
                    borderWidth: 0
                }]
            },
            options: {
                indexAxis: 'y',
                responsive: true,
                maintainAspectRatio: false,
                scales: {
                    x: {
                        beginAtZero: true,
                        grid: { color: 'rgba(122, 122, 122, 0.1)', drawBorder: false },
                        ticks: { color: '#1a1a1a', font: { family: 'Inter', size: 10 } }
                    },
                    y: {
                        grid: { display: false },
                        ticks: { color: '#1a1a1a', font: { family: 'Inter', size: 10 } }
                    }
                }
            }
        });

        // Update Macros Chart with data from PHP
        if (window.macrosData) {
            macrosChart.data.datasets[0].data = [
                window.macrosData.protein,
                window.macrosData.carbs,
                window.macrosData.fats
            ];
            macrosChart.update();
        }
    }
});
// Global variables to store the last and second-to-last values for each metric
let lastResultWeight = [], lastResultSecondWeight = [];
let lastResultWaist = [], lastResultSecondWaist = [];
let lastResultSleep = -1, lastResultSecondSleep = -1;
let lastResultStress = -1, lastResultSecondStress = -1;
let lastResultHrv = -1, lastResultSecondHrv = -1;
let lastResultFatigue = -1, lastResultSecondFatigue = -1;
let lastResultHunger = -1, lastResultSecondHunger = -1;
let lastResultRecovery = -1, lastResultSecondRecovery = -1;
let lastResultStrength = -1, lastResultSecondStrength = -1;
let lastResultEnergy = -1, lastResultSecondEnergy = -1;
let lastResultDigestion = -1, lastResultSecondDigestion = -1;
let lastResultSteps = -1, lastResultSecondSteps = -1;
let lastResultGlucoseLevel = -1, lastResultSecondGlucoseLevel = -1;

// Flags to track if metrics are available
let chkWeightVal = 0, chkWaistVal = 0, chkSleepVal = 0, chkStressVal = 0, chkHrvVal = 0, chkFatigueVal = 0, chkHungerVal = 0, chkRecoveryVal = 0, chkStrengthVal = 0, chkEnergyVal = 0, chkDigestionVal = 0, chkStepsVal = 0, chkGlucoseLevelVal = 0;

// Function to calculate percentage difference for Weight
function differentStartCurrentWeight() {
    let startWeight = lastResultWeight.length > 0 ? parseFloat(lastResultWeight[0]) : 0;
    let currentWeight = lastResultSecondWeight.length > 0 ? parseFloat(lastResultSecondWeight[0]) : 0;
    let percentWeightt = document.getElementById('percentWeightt');
    if (!percentWeightt) return;

    let perc = "";
    if (startWeight > 0 || currentWeight > 0) {
        document.querySelector('#client-data-details-table-new tr#weight').style.display = 'table-row';
        chkWeightVal = 1;

        if (startWeight > currentWeight) {
            let differentWeights = startWeight - currentWeight;
            perc = ((differentWeights / startWeight) * 100).toFixed(1) + '%';
            percentWeightt.innerHTML += perc;
        } else {
            let differentWeights = currentWeight - startWeight;
            if (currentWeight === 0) {
                if (startWeight !== 0) {
                    perc = 100 + '%';
                } else {
                    perc = 'N/A';
                }
            } else {
                perc = ((differentWeights / currentWeight) * 100).toFixed(1) + '%';
            }
            if (perc === 'N/A') {
                percentWeightt.previousElementSibling.style.display = 'none';
                percentWeightt.parentElement.style.backgroundColor = '#a1a5b7';
            }
            percentWeightt.innerHTML += perc;
        }
    } else {
        document.querySelector('#client-data-details-table-new tr#weight').style.display = 'none';
        chkWeightVal = 0;
    }
}

// Function to calculate percentage difference for Waist
function differentStartCurrentWaist() {
    let startWaist = lastResultWaist.length > 0 ? parseFloat(lastResultWaist[0]) : 0;
    let currentWaist = lastResultSecondWaist.length > 0 ? parseFloat(lastResultSecondWaist[0]) : 0;
    let percentWaist = document.getElementById('percentWaist');
    if (!percentWaist) return;

    let perc = "";
    if (startWaist > 0 || currentWaist > 0) {
        document.querySelector('#client-data-details-table-new tr#waist').style.display = 'table-row';
        chkWaistVal = 1;

        if (startWaist > currentWaist) {
            let differentWaists = startWaist - currentWaist;
            perc = ((differentWaists / startWaist) * 100).toFixed(1) + '%';
            percentWaist.innerHTML += perc;
        } else {
            let differentWaists = currentWaist - startWaist;
            if (currentWaist === 0) {
                if (startWaist !== 0) {
                    perc = 100 + '%';
                } else {
                    perc = 'N/A';
                }
            } else {
                perc = ((differentWaists / currentWaist) * 100).toFixed(1) + '%';
            }
            if (perc === 'N/A') {
                percentWaist.previousElementSibling.style.display = 'none';
                percentWaist.parentElement.style.backgroundColor = '#a1a5b7';
            }
            percentWaist.innerHTML += perc;
        }
    } else {
        document.querySelector('#client-data-details-table-new tr#waist').style.display = 'none';
        chkWaistVal = 0;
    }
}

// Function to calculate percentage difference for Sleep
function differentStartCurrentSleep() {
    let startSleep = lastResultSleep >= 0 ? parseFloat(lastResultSleep) : 0;
    let currentSleep = lastResultSecondSleep >= 0 ? parseFloat(lastResultSecondSleep) : 0;
    let percentSleep = document.getElementById('percentSleep');
    if (!percentSleep) return;

    let perc = "";
    if (startSleep > 0 || currentSleep > 0) {
        document.querySelector('#client-data-details-table-new tr#sleep').style.display = 'table-row';
        chkSleepVal = 1;

        if (startSleep > currentSleep) {
            let differentSleeps = startSleep - currentSleep;
            perc = ((differentSleeps / startSleep) * 100).toFixed(1) + '%';
            percentSleep.innerHTML += perc;
        } else {
            let differentSleeps = currentSleep - startSleep;
            if (currentSleep === 0) {
                if (startSleep !== 0) {
                    perc = 100 + '%';
                } else {
                    perc = 'N/A';
                }
            } else {
                perc = ((differentSleeps / currentSleep) * 100).toFixed(1) + '%';
            }
            if (perc === 'N/A') {
                percentSleep.previousElementSibling.style.display = 'none';
                percentSleep.parentElement.style.backgroundColor = '#a1a5b7';
            }
            percentSleep.innerHTML += perc;
        }
    } else {
        document.querySelector('#client-data-details-table-new tr#sleep').style.display = 'none';
        chkSleepVal = 0;
    }
}

// Function to calculate percentage difference for Stress
function differentStartCurrentStress() {
    let startStress = lastResultStress >= 0 ? parseFloat(lastResultStress) : 0;
    let currentStress = lastResultSecondStress >= 0 ? parseFloat(lastResultSecondStress) : 0;
    let percentStress = document.getElementById('percentStress');
    if (!percentStress) return;

    let perc = "";
    if (startStress > 0 || currentStress > 0) {
        document.querySelector('#client-data-details-table-new tr#stress').style.display = 'table-row';
        chkStressVal = 1;

        if (startStress > currentStress) {
            let differentStress = startStress - currentStress;
            perc = ((differentStress / startStress) * 100).toFixed(1) + '%';
            percentStress.innerHTML += perc;
        } else {
            let differentStress = currentStress - startStress;
            if (currentStress === 0) {
                if (startStress !== 0) {
                    perc = 100 + '%';
                } else {
                    perc = 'N/A';
                }
            } else {
                perc = ((differentStress / currentStress) * 100).toFixed(1) + '%';
            }
            if (perc === 'N/A') {
                percentStress.previousElementSibling.style.display = 'none';
                percentStress.parentElement.style.backgroundColor = '#a1a5b7';
            }
            percentStress.innerHTML += perc;
        }
    } else {
        document.querySelector('#client-data-details-table-new tr#stress').style.display = 'none';
        chkStressVal = 0;
    }
}

// Function to calculate percentage difference for HRV
function differentStartCurrentHrv() {
    let startHrv = lastResultHrv >= 0 ? parseFloat(lastResultHrv) : 0;
    let currentHrv = lastResultSecondHrv >= 0 ? parseFloat(lastResultSecondHrv) : 0;
    let percentHrv = document.getElementById('percentHrv');
    if (!percentHrv) return;

    let perc = "";
    if (startHrv > 0 || currentHrv > 0) {
        document.querySelector('#client-data-details-table-new tr#hrv').style.display = 'table-row';
        chkHrvVal = 1;

        if (startHrv > currentHrv) {
            let differentHrv = startHrv - currentHrv;
            perc = ((differentHrv / startHrv) * 100).toFixed(1) + '%';
            percentHrv.innerHTML += perc;
        } else {
            let differentHrv = currentHrv - startHrv;
            if (currentHrv === 0) {
                if (startHrv !== 0) {
                    perc = 100 + '%';
                } else {
                    perc = 'N/A';
                }
            } else {
                perc = ((differentHrv / currentHrv) * 100).toFixed(1) + '%';
            }
            if (perc === 'N/A') {
                percentHrv.previousElementSibling.style.display = 'none';
                percentHrv.parentElement.style.backgroundColor = '#a1a5b7';
            }
            percentHrv.innerHTML += perc;
        }
    } else {
        document.querySelector('#client-data-details-table-new tr#hrv').style.display = 'none';
        chkHrvVal = 0;
    }
}

// Function to calculate percentage difference for Fatigue
function differentStartCurrentFatigue() {
    let startFatigue = lastResultFatigue >= 0 ? parseFloat(lastResultFatigue) : 0;
    let currentFatigue = lastResultSecondFatigue >= 0 ? parseFloat(lastResultSecondFatigue) : 0;
    let percentFatigue = document.getElementById('percentFatigue');
    if (!percentFatigue) return;

    let perc = "";
    if (startFatigue > 0 || currentFatigue > 0) {
        document.querySelector('#client-data-details-table-new tr#fatigue').style.display = 'table-row';
        chkFatigueVal = 1;

        if (startFatigue > currentFatigue) {
            let differentFatigue = startFatigue - currentFatigue;
            perc = ((differentFatigue / startFatigue) * 100).toFixed(1) + '%';
            percentFatigue.innerHTML += perc;
        } else {
            let differentFatigue = currentFatigue - startFatigue;
            if (currentFatigue === 0) {
                if (startFatigue !== 0) {
                    perc = 100 + '%';
                } else {
                    perc = 'N/A';
                }
            } else {
                perc = ((differentFatigue / currentFatigue) * 100).toFixed(1) + '%';
            }
            if (perc === 'N/A') {
                percentFatigue.previousElementSibling.style.display = 'none';
                percentFatigue.parentElement.style.backgroundColor = '#a1a5b7';
            }
            percentFatigue.innerHTML += perc;
        }
    } else {
        document.querySelector('#client-data-details-table-new tr#fatigue').style.display = 'none';
        chkFatigueVal = 0;
    }
}

// Function to calculate percentage difference for Hunger
function differentStartCurrentHunger() {
    let startHunger = lastResultHunger >= 0 ? parseFloat(lastResultHunger) : 0;
    let currentHunger = lastResultSecondHunger >= 0 ? parseFloat(lastResultSecondHunger) : 0;
    let percentHunger = document.getElementById('percentHunger');
    if (!percentHunger) return;

    let perc = "";
    if (startHunger > 0 || currentHunger > 0) {
        document.querySelector('#client-data-details-table-new tr#hunger').style.display = 'table-row';
        chkHungerVal = 1;

        if (startHunger > currentHunger) {
            let differentHunger = startHunger - currentHunger;
            perc = ((differentHunger / startHunger) * 100).toFixed(1) + '%';
            percentHunger.innerHTML += perc;
        } else {
            let differentHunger = currentHunger - startHunger;
            if (currentHunger === 0) {
                if (startHunger !== 0) {
                    perc = 100 + '%';
                } else {
                    perc = 'N/A';
                }
            } else {
                perc = ((differentHunger / currentHunger) * 100).toFixed(1) + '%';
            }
            if (perc === 'N/A') {
                percentHunger.previousElementSibling.style.display = 'none';
                percentHunger.parentElement.style.backgroundColor = '#a1a5b7';
            }
            percentHunger.innerHTML += perc;
        }
    } else {
        document.querySelector('#client-data-details-table-new tr#hunger').style.display = 'none';
        chkHungerVal = 0;
    }
}

// Function to calculate percentage difference for Recovery
function differentStartCurrentRecovery() {
    let startRecovery = lastResultRecovery >= 0 ? parseFloat(lastResultRecovery) : 0;
    let currentRecovery = lastResultSecondRecovery >= 0 ? parseFloat(lastResultSecondRecovery) : 0;
    let percentRecovery = document.getElementById('percentRecovery');
    if (!percentRecovery) return;

    let perc = "";
    if (startRecovery > 0 || currentRecovery > 0) {
        document.querySelector('#client-data-details-table-new tr#recovery').style.display = 'table-row';
        chkRecoveryVal = 1;

        if (startRecovery > currentRecovery) {
            let differentRecovery = startRecovery - currentRecovery;
            perc = ((differentRecovery / startRecovery) * 100).toFixed(1) + '%';
            percentRecovery.innerHTML += perc;
        } else {
            let differentRecovery = currentRecovery - startRecovery;
            if (currentRecovery === 0) {
                if (startRecovery !== 0) {
                    perc = 100 + '%';
                } else {
                    perc = 'N/A';
                }
            } else {
                perc = ((differentRecovery / currentRecovery) * 100).toFixed(1) + '%';
            }
            if (perc === 'N/A') {
                percentRecovery.previousElementSibling.style.display = 'none';
                percentRecovery.parentElement.style.backgroundColor = '#a1a5b7';
            }
            percentRecovery.innerHTML += perc;
        }
    } else {
        document.querySelector('#client-data-details-table-new tr#recovery').style.display = 'none';
        chkRecoveryVal = 0;
    }
}

// Function to calculate percentage difference for Strength
function differentStartCurrentStrength() {
    let startStrength = lastResultStrength >= 0 ? parseFloat(lastResultStrength) : 0;
    let currentStrength = lastResultSecondStrength >= 0 ? parseFloat(lastResultSecondStrength) : 0;
    let percentStrength = document.getElementById('percentStrength');
    if (!percentStrength) return;

    let perc = "";
    if (startStrength > 0 || currentStrength > 0) {
        document.querySelector('#client-data-details-table-new tr#strength').style.display = 'table-row';
        chkStrengthVal = 1;

        if (startStrength > currentStrength) {
            let differentStrength = startStrength - currentStrength;
            perc = ((differentStrength / startStrength) * 100).toFixed(1) + '%';
            percentStrength.innerHTML += perc;
        } else {
            let differentStrength = currentStrength - startStrength;
            if (currentStrength === 0) {
                if (startStrength !== 0) {
                    perc = 100 + '%';
                } else {
                    perc = 'N/A';
                }
            } else {
                perc = ((differentStrength / currentStrength) * 100).toFixed(1) + '%';
            }
            if (perc === 'N/A') {
                percentStrength.previousElementSibling.style.display = 'none';
                percentStrength.parentElement.style.backgroundColor = '#a1a5b7';
            }
            percentStrength.innerHTML += perc;
        }
    } else {
        document.querySelector('#client-data-details-table-new tr#strength').style.display = 'none';
        chkStrengthVal = 0;
    }
}

// Function to calculate percentage difference for Energy
function differentStartCurrentEnergy() {
    let startEnergy = lastResultEnergy >= 0 ? parseFloat(lastResultEnergy) : 0;
    let currentEnergy = lastResultSecondEnergy >= 0 ? parseFloat(lastResultSecondEnergy) : 0;
    let percentEnergy = document.getElementById('percentEnergy');
    if (!percentEnergy) return;

    let perc = "";
    if (startEnergy > 0 || currentEnergy > 0) {
        document.querySelector('#client-data-details-table-new tr#energy').style.display = 'table-row';
        chkEnergyVal = 1;

        if (startEnergy > currentEnergy) {
            let differentEnergy = startEnergy - currentEnergy;
            perc = ((differentEnergy / startEnergy) * 100).toFixed(1) + '%';
            percentEnergy.innerHTML += perc;
        } else {
            let differentEnergy = currentEnergy - startEnergy;
            if (currentEnergy === 0) {
                if (startEnergy !== 0) {
                    perc = 100 + '%';
                } else {
                    perc = 'N/A';
                }
            } else {
                perc = ((differentEnergy / currentEnergy) * 100).toFixed(1) + '%';
            }
            if (perc === 'N/A') {
                percentEnergy.previousElementSibling.style.display = 'none';
                percentEnergy.parentElement.style.backgroundColor = '#a1a5b7';
            }
            percentEnergy.innerHTML += perc;
        }
    } else {
        document.querySelector('#client-data-details-table-new tr#energy').style.display = 'none';
        chkEnergyVal = 0;
    }
}

// Function to calculate percentage difference for Digestion
function differentStartCurrentDigestion() {
    let startDigestion = lastResultDigestion >= 0 ? parseFloat(lastResultDigestion) : 0;
    let currentDigestion = lastResultSecondDigestion >= 0 ? parseFloat(lastResultSecondDigestion) : 0;
    let percentDigestion = document.getElementById('percentDigestion');
    if (!percentDigestion) return;

    let perc = "";
    if (startDigestion > 0 || currentDigestion > 0) {
        document.querySelector('#client-data-details-table-new tr#digestion').style.display = 'table-row';
        chkDigestionVal = 1;

        if (startDigestion > currentDigestion) {
            let differentDigestion = startDigestion - currentDigestion;
            perc = ((differentDigestion / startDigestion) * 100).toFixed(1) + '%';
            percentDigestion.innerHTML += perc;
        } else {
            let differentDigestion = currentDigestion - startDigestion;
            if (currentDigestion === 0) {
                if (startDigestion !== 0) {
                    perc = 100 + '%';
                } else {
                    perc = 'N/A';
                }
            } else {
                perc = ((differentDigestion / currentDigestion) * 100).toFixed(1) + '%';
            }
            if (perc === 'N/A') {
                percentDigestion.previousElementSibling.style.display = 'none';
                percentDigestion.parentElement.style.backgroundColor = '#a1a5b7';
            }
            percentDigestion.innerHTML += perc;
        }
    } else {
        document.querySelector('#client-data-details-table-new tr#digestion').style.display = 'none';
        chkDigestionVal = 0;
    }
}

// Function to calculate percentage difference for Steps
function differentStartCurrentSteps() {
    let startSteps = lastResultSteps >= 0 ? parseFloat(lastResultSteps) : 0;
    let currentSteps = lastResultSecondSteps >= 0 ? parseFloat(lastResultSecondSteps) : 0;
    let percentSteps = document.getElementById('percentSteps');
    if (!percentSteps) return;

    let perc = "";
    if (startSteps > 0 || currentSteps > 0) {
        document.querySelector('#client-data-details-table-new tr#steps').style.display = 'table-row';
        chkStepsVal = 1;

        if (startSteps > currentSteps) {
            let differentSteps = startSteps - currentSteps;
            perc = ((differentSteps / startSteps) * 100).toFixed(1) + '%';
            percentSteps.innerHTML += perc;
        } else {
            let differentSteps = currentSteps - startSteps;
            if (currentSteps === 0) {
                if (startSteps !== 0) {
                    perc = 100 + '%';
                } else {
                    perc = 'N/A';
                }
            } else {
                perc = ((differentSteps / currentSteps) * 100).toFixed(1) + '%';
            }
            if (perc === 'N/A') {
                percentSteps.previousElementSibling.style.display = 'none';
                percentSteps.parentElement.style.backgroundColor = '#a1a5b7';
            }
            percentSteps.innerHTML += perc;
        }
    } else {
        document.querySelector('#client-data-details-table-new tr#steps').style.display = 'none';
        chkStepsVal = 0;
    }
}

// Function to calculate percentage difference for Glucose Level
function differentStartCurrentGlucoseLevel() {
    let startGlucoseLevel = lastResultGlucoseLevel >= 0 ? parseFloat(lastResultGlucoseLevel) : 0;
    let currentGlucoseLevel = lastResultSecondGlucoseLevel >= 0 ? parseFloat(lastResultSecondGlucoseLevel) : 0;
    let percentGlucoseLevel = document.getElementById('percentGlucoseLevel');
    if (!percentGlucoseLevel) return;

    let perc = "";
    if (startGlucoseLevel > 0 || currentGlucoseLevel > 0) {
        document.querySelector('#client-data-details-table-new tr#glucose_level').style.display = 'table-row';
        chkGlucoseLevelVal = 1;

        if (startGlucoseLevel > currentGlucoseLevel) {
            let differentGlucoseLevel = startGlucoseLevel - currentGlucoseLevel;
            perc = ((differentGlucoseLevel / startGlucoseLevel) * 100).toFixed(1) + '%';
            percentGlucoseLevel.innerHTML += perc;
        } else {
            let differentGlucoseLevel = currentGlucoseLevel - startGlucoseLevel;
            if (currentGlucoseLevel === 0) {
                if (startGlucoseLevel !== 0) {
                    perc = 100 + '%';
                } else {
                    perc = 'N/A';
                }
            } else {
                perc = ((differentGlucoseLevel / currentGlucoseLevel) * 100).toFixed(1) + '%';
            }
            if (perc === 'N/A') {
                percentGlucoseLevel.previousElementSibling.style.display = 'none';
                percentGlucoseLevel.parentElement.style.backgroundColor = '#a1a5b7';
            }
            percentGlucoseLevel.innerHTML += perc;
        }
    } else {
        document.querySelector('#client-data-details-table-new tr#glucose_level').style.display = 'none';
        chkGlucoseLevelVal = 0;
    }
}

// Initialize all metric calculations on page load
document.addEventListener('DOMContentLoaded', function() {
    differentStartCurrentWeight();
    differentStartCurrentWaist();
    differentStartCurrentSleep();
    differentStartCurrentStress();
    differentStartCurrentHrv();
    differentStartCurrentFatigue();
    differentStartCurrentHunger();
    differentStartCurrentRecovery();
    differentStartCurrentStrength();
    differentStartCurrentEnergy();
    differentStartCurrentDigestion();
    differentStartCurrentSteps();
    differentStartCurrentGlucoseLevel();
});

// Function to handle workout plan selection (adapted for STRV)
function handleWorkoutPlanSelection() {
    document.querySelectorAll('.workoutlist').forEach(function(element) {
        element.addEventListener('change', function() {
            let id = this.value;
            let action = document.querySelector('#client-workout_plan-view-button')?.dataset.action || 'workout_plan';
            let isDashboard = document.querySelector('#client-workout_plan-view-button')?.dataset.isdashboard || 'true';
            let user_id = document.querySelector('#client-workout_plan-view-button')?.dataset.user_id || '';

            // Show loading indicator
            let clientDataList = document.querySelector('#client-data-list');
            if (clientDataList) {
                clientDataList.innerHTML = '<p>Loading...</p>';
            }

            // Prepare date range for the request
            let dateRange = {
                startDate: moment().subtract(0, 'weeks').startOf('week'),
                endDate: moment().subtract(0, 'weeks').endOf('week')
            };
            let requestData = {
                startDate: dateRange.startDate.format('YYYY-MM-DD'),
                endDate: dateRange.endDate.format('YYYY-MM-DD')
            };

            // Make AJAX request to fetch workout plan data
            fetch('/fetch_workout_plan', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify({
                    action: action,
                    id: id,
                    isDashboard: isDashboard,
                    user_id: user_id,
                    date_interval: requestData
                })
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    // Update the client data list with the new workout plan
                    if (clientDataList) {
                        clientDataList.innerHTML = data.html || '<p>No workout plan available.</p>';
                    }
                    // Update active state for buttons
                    document.querySelectorAll('.select-client-action').forEach(btn => btn.classList.remove('active'));
                    if (action === 'workout_plan') {
                        let workoutButton = document.querySelector('#client-workout_plan-view-button');
                        if (workoutButton) workoutButton.classList.add('active');
                    } else {
                        let selectedButton = document.querySelector(`#${id}`);
                        if (selectedButton) selectedButton.classList.add('active');
                    }
                } else {
                    alert('Error loading workout plan: ' + (data.message || 'Unknown error'));
                }
            })
            .catch(error => {
                console.error('Error fetching workout plan:', error);
                if (clientDataList) {
                    clientDataList.innerHTML = '<p>Error loading workout plan.</p>';
                }
            });
        });
    });
}

// Initialize workout plan selection on page load
document.addEventListener('DOMContentLoaded', function() {
    handleWorkoutPlanSelection();
});
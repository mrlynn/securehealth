(function() {
    const appointmentsEndpoint = '/api/appointments';
    const patientsEndpoint = '/api/patients?page=1&limit=50';

    document.addEventListener('DOMContentLoaded', function() {
        const config = window.appointmentConfig || {};

        const form = document.getElementById('appointmentForm');
        const patientSelect = document.getElementById('patientSelect');
        const scheduledInput = document.getElementById('scheduledAt');
        const notesInput = document.getElementById('appointmentNotes');
        const alertBox = document.getElementById('appointmentAlert');
        const tableBody = document.querySelector('#appointmentsTable tbody');
        const emptyState = document.getElementById('appointmentsEmpty');
        const refreshButton = document.getElementById('refreshAppointments');

        if (!form || !patientSelect || !scheduledInput || !tableBody) {
            return;
        }

        hydratePatients(Array.isArray(config.patients) ? config.patients : []);
        reloadPatients();
        fetchAppointments();

        form.addEventListener('submit', function(event) {
            event.preventDefault();

            const patientId = patientSelect.value;
            const scheduledAt = scheduledInput.value;
            const notes = notesInput.value.trim();

            if (!patientId || !scheduledAt) {
                showAlert('danger', 'Please choose a patient and select a date/time.');
                return;
            }

            showAlert('info', 'Scheduling appointment...');

            fetch(appointmentsEndpoint, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json'
                },
                credentials: 'include',
                body: JSON.stringify({
                    patientId: patientId,
                    scheduledAt: scheduledAt,
                    notes: notes || null
                })
            })
            .then(handleResponse)
            .then(function(response) {
                if (!response.ok) {
                    throw response;
                }
                return response.json();
            })
            .then(function() {
                showAlert('success', 'Appointment scheduled successfully.');
                form.reset();
                patientSelect.selectedIndex = 0;
                fetchAppointments(true);
            })
            .catch(function(errorResponse) {
                handleJsonError(errorResponse, 'Unable to create appointment.');
            });
        });

        if (refreshButton) {
            refreshButton.addEventListener('click', function() {
                fetchAppointments(true);
            });
        }

        function reloadPatients() {
            fetch(patientsEndpoint, {
                method: 'GET',
                credentials: 'include'
            })
            .then(handleResponse)
            .then(function(response) {
                if (!response.ok) {
                    throw response;
                }
                return response.json();
            })
            .then(function(payload) {
                const patients = Array.isArray(payload && payload.patients) ? payload.patients : [];
                hydratePatients(patients);
            })
            .catch(function(errorResponse) {
                handleJsonError(errorResponse, 'Unable to load patients list.');
            });
        }

        function fetchAppointments(force) {
            if (!force) {
                showAlert(null);
            }

            fetch(appointmentsEndpoint, {
                method: 'GET',
                credentials: 'include'
            })
            .then(handleResponse)
            .then(function(response) {
                if (!response.ok) {
                    throw response;
                }
                return response.json();
            })
            .then(function(payload) {
                const appointments = Array.isArray(payload && payload.appointments) ? payload.appointments : [];
                renderAppointments(appointments);
            })
            .catch(function(errorResponse) {
                handleJsonError(errorResponse, 'Unable to load appointments.');
                renderAppointments([]);
            });
        }

        function hydratePatients(patients) {
            const placeholderText = 'Select a patient';
            patientSelect.innerHTML = '';

            const placeholder = document.createElement('option');
            placeholder.value = '';
            placeholder.textContent = placeholderText;
            placeholder.disabled = true;
            placeholder.selected = true;
            patientSelect.appendChild(placeholder);

            if (!Array.isArray(patients) || patients.length === 0) {
                return;
            }

            const seen = new Set();

            patients
                .map(function(patient) {
                    if (!patient || !patient.id) {
                        return null;
                    }

                    const id = String(patient.id);
                    if (seen.has(id)) {
                        return null;
                    }
                    seen.add(id);

                    const firstName = patient.firstName || patient.first_name || '';
                    const lastName = patient.lastName || patient.last_name || '';
                    const email = patient.email || patient.contactEmail || '';

                    const parts = [lastName, firstName].filter(Boolean);
                    const label = parts.length ? parts.join(', ') : (email || id);

                    return {
                        id: id,
                        label: label
                    };
                })
                .filter(Boolean)
                .sort(function(a, b) {
                    return a.label.localeCompare(b.label, undefined, { sensitivity: 'base' });
                })
                .forEach(function(optionData) {
                    const option = document.createElement('option');
                    option.value = optionData.id;
                    option.textContent = optionData.label;
                    patientSelect.appendChild(option);
                });
        }

        function renderAppointments(appointments) {
            tableBody.innerHTML = '';

            if (!Array.isArray(appointments) || appointments.length === 0) {
                if (emptyState) {
                    emptyState.classList.remove('d-none');
                }
                return;
            }

            if (emptyState) {
                emptyState.classList.add('d-none');
            }

            appointments.forEach(function(appointment) {
                const row = document.createElement('tr');
                row.innerHTML = [
                    `<td>${escapeHtml(appointment.patientFullName || 'Unknown')}</td>`,
                    `<td>${formatDate(appointment.scheduledAt)}</td>`,
                    `<td>${escapeHtml(appointment.notes || '')}</td>`,
                    `<td>${escapeHtml(appointment.createdBy || '')}</td>`
                ].join('');

                tableBody.appendChild(row);
            });
        }

        function showAlert(type, message) {
            if (!alertBox) {
                return;
            }

            if (!type || !message) {
                alertBox.classList.add('d-none');
                alertBox.className = 'alert d-none';
                alertBox.textContent = '';
                return;
            }

            alertBox.className = `alert alert-${type}`;
            alertBox.textContent = message;
            alertBox.classList.remove('d-none');
        }

        function formatDate(value) {
            if (!value) {
                return '';
            }

            try {
                const date = new Date(value);
                return isNaN(date.getTime()) ? value : date.toLocaleString();
            } catch (error) {
                return value;
            }
        }

        function escapeHtml(value) {
            if (!value) {
                return '';
            }
            return String(value)
                .replace(/&/g, '&amp;')
                .replace(/</g, '&lt;')
                .replace(/>/g, '&gt;')
                .replace(/"/g, '&quot;')
                .replace(/'/g, '&#039;');
        }

        function handleResponse(response) {
            if (response.status === 401) {
                showAlert('danger', 'Your session has expired. Please log in again.');
                redirectToLogin();
            }

            if (response.status === 403) {
                showAlert('danger', 'You are not allowed to access the scheduling service.');
                redirectToLogin();
            }

            return response;
        }

        function handleJsonError(response, defaultMessage) {
            if (response && typeof response.json === 'function') {
                response.json().then(function(data) {
                    const message = data && data.message ? data.message : defaultMessage;
                    showAlert('danger', message);
                }).catch(function() {
                    showAlert('danger', defaultMessage);
                });
            } else {
                showAlert('danger', defaultMessage);
            }
        }

        function redirectToLogin() {
            setTimeout(function() {
                window.location.href = '/login.html';
            }, 1500);
        }
    });
})();

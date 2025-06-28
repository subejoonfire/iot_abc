<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Monitoring IoT ABC</title>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <style>
        body {
            font-family: Arial, sans-serif;
        }

        .container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 20px;
        }

        .card {
            background: #f9f9f9;
            border-radius: 8px;
            padding: 20px;
            margin-bottom: 20px;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
        }

        .status {
            display: flex;
            gap: 20px;
        }

        .status-box {
            flex: 1;
            text-align: center;
            padding: 15px;
            border-radius: 8px;
        }

        .hujan {
            background: #e3f2fd;
        }

        .cahaya {
            background: #fff8e1;
        }

        .lamp {
            background: #e8f5e9;
        }

        .btn {
            padding: 10px 20px;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            font-size: 16px;
        }

        .btn-on {
            background: #4caf50;
            color: white;
        }

        .btn-off {
            background: #f44336;
            color: white;
        }

        .btn-auto {
            background: #2196f3;
            color: white;
        }

        .active {
            opacity: 0.7;
            transform: scale(0.98);
        }
    </style>
</head>

<body>
    <div class="container">
        <h1>Monitoring Sensor & Kontrol Lampu</h1>

        <div class="card">
            <h2>Status Terkini</h2>
            <div class="status">
                <div class="status-box hujan">
                    <h3>Sensor Hujan</h3>
                    <p id="hujan-status">Loading...</p>
                </div>
                <div class="status-box cahaya">
                    <h3>Sensor Cahaya</h3>
                    <p id="cahaya-status">Loading...</p>
                </div>
                <div class="status-box lamp">
                    <h3>Lampu</h3>
                    <p id="lamp-status">Loading...</p>
                    <p id="lamp-mode">Mode: Auto</p>
                    <button id="btn-on" class="btn btn-on">ON</button>
                    <button id="btn-off" class="btn btn-off">OFF</button>
                    <button id="btn-auto" class="btn btn-auto">AUTO MODE</button>
                </div>
            </div>
        </div>

        <div class="card">
            <h2>Riwayat Sensor</h2>
            <canvas id="sensorChart"></canvas>
        </div>
    </div>

    <script>
        // DOM Elements
        const hujanStatus = document.getElementById('hujan-status');
        const cahayaStatus = document.getElementById('cahaya-status');
        const lampStatus = document.getElementById('lamp-status');
        const lampMode = document.getElementById('lamp-mode');
        const btnOn = document.getElementById('btn-on');
        const btnOff = document.getElementById('btn-off');
        const btnAuto = document.getElementById('btn-auto');

        // Inisialisasi Chart
        const ctx = document.getElementById('sensorChart').getContext('2d');
        const chart = new Chart(ctx, {
            type: 'line',
            data: {
                labels: [],
                datasets: [{
                        label: 'Hujan (Ya/Tidak)',
                        data: [],
                        borderColor: '#42a5f5',
                        tension: 0.1
                    },
                    {
                        label: 'Cahaya (Gelap/Terang)',
                        data: [],
                        borderColor: '#ffca28',
                        tension: 0.1
                    }
                ]
            },
            options: {
                scales: {
                    y: {
                        suggestedMin: 0,
                        suggestedMax: 1
                    }
                }
            }
        });

        // Fungsi update data sensor
        async function updateSensorData() {
            try {
                const response = await fetch('api.php?action=get_sensor');
                const data = await response.json();

                if (data.length > 0) {
                    const latest = data[0];
                    hujanStatus.textContent = latest.hujan_status;
                    cahayaStatus.textContent = latest.cahaya_status;

                    const labels = [];
                    const hujanData = [];
                    const cahayaData = [];

                    data.reverse().forEach(item => {
                        labels.push(new Date(item.timestamp).toLocaleTimeString());
                        hujanData.push(item.hujan_status === 'Ya' ? 1 : 0);
                        cahayaData.push(item.cahaya_status === 'Gelap' ? 1 : 0);
                    });

                    chart.data.labels = labels;
                    chart.data.datasets[0].data = hujanData;
                    chart.data.datasets[1].data = cahayaData;
                    chart.update();
                }
            } catch (error) {
                console.error('Error fetching sensor data:', error);
            }
        }

        // Fungsi update status lampu
        async function updateLampStatus() {
            try {
                const response = await fetch('api.php?action=get_lamp');
                const data = await response.json();

                if (data.manual_mode) {
                    lampStatus.textContent = data.lamp_status ? 'Menyala' : 'Mati';
                    lampMode.textContent = "Mode: Manual";
                } else {
                    lampStatus.textContent = "Auto Mode";
                    lampMode.textContent = "Mode: Auto";
                }

                btnOn.disabled = data.manual_mode && data.lamp_status;
                btnOff.disabled = data.manual_mode && !data.lamp_status;
                btnAuto.disabled = !data.manual_mode;

            } catch (error) {
                console.error('Error fetching lamp status:', error);
            }
        }

        // Kontrol lampu
        async function controlLamp(manual, status) {
            try {
                const formData = new FormData();
                formData.append('manual', manual ? 1 : 0);
                formData.append('status', status ? 1 : 0);

                await fetch('api.php?action=update_lamp', {
                    method: 'POST',
                    body: formData
                });

                updateLampStatus();
            } catch (error) {
                console.error('Error controlling lamp:', error);
            }
        }

        // Event listeners
        btnOn.addEventListener('click', () => controlLamp(true, true));
        btnOff.addEventListener('click', () => controlLamp(true, false));
        btnAuto.addEventListener('click', () => controlLamp(false, false));

        // Auto refresh
        setInterval(updateSensorData, 5000);
        setInterval(updateLampStatus, 3000);

        // Initial load
        updateSensorData();
        updateLampStatus();
    </script>
</body>

</html>
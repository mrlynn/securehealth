<?php
header('Content-Type: text/html');
?>
<!DOCTYPE html>
<html>
<head>
    <title>Login Test</title>
    <style>
        body { font-family: Arial, sans-serif; padding: 20px; }
        .container { max-width: 800px; margin: 0 auto; }
        pre { background: #f4f4f4; padding: 10px; overflow: auto; }
        .success { color: green; }
        .error { color: red; }
        button { margin-top: 10px; padding: 5px 10px; }
    </style>
</head>
<body>
    <div class="container">
        <h1>Login API Test</h1>
        
        <h2>Test Login</h2>
        <div>
            <button onclick="testLogin('doctor@example.com', 'doctor')">Test Doctor Login</button>
            <button onclick="testLogin('nurse@example.com', 'nurse')">Test Nurse Login</button>
            <button onclick="testLogin('receptionist@example.com', 'receptionist')">Test Receptionist Login</button>
        </div>
        
        <h3>Response:</h3>
        <pre id="loginResponse"></pre>
        
        <h2>Local Storage</h2>
        <div>
            <button onclick="showLocalStorage()">Show User in Local Storage</button>
            <button onclick="clearLocalStorage()">Clear Local Storage</button>
        </div>
        
        <h3>Local Storage Content:</h3>
        <pre id="storageContent"></pre>
        
        <h2>Redirect Test</h2>
        <div>
            <button onclick="testRedirect()">Test Redirect to Patients</button>
        </div>
    </div>

    <script>
        function testLogin(email, password) {
            document.getElementById('loginResponse').innerHTML = 'Sending request...';
            
            fetch('/api/login', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify({
                    _username: email,
                    _password: password
                })
            })
            .then(response => response.json())
            .then(data => {
                document.getElementById('loginResponse').innerHTML = 
                    '<span class="' + (data.success ? 'success' : 'error') + '">' + 
                    JSON.stringify(data, null, 2) + '</span>';
                
                if (data.success) {
                    const user = {
                        email: email,
                        username: data.username || email,
                        roles: data.roles || []
                    };
                    
                    localStorage.setItem('securehealth_user', JSON.stringify(user));
                    showLocalStorage();
                }
            })
            .catch(error => {
                document.getElementById('loginResponse').innerHTML = 
                    '<span class="error">Error: ' + error.message + '</span>';
            });
        }
        
        function showLocalStorage() {
            const user = localStorage.getItem('securehealth_user');
            if (user) {
                try {
                    const parsed = JSON.parse(user);
                    document.getElementById('storageContent').innerHTML = 
                        '<span class="success">' + JSON.stringify(parsed, null, 2) + '</span>';
                } catch (e) {
                    document.getElementById('storageContent').innerHTML = 
                        '<span class="error">Invalid JSON: ' + user + '</span>';
                }
            } else {
                document.getElementById('storageContent').innerHTML = 
                    '<span class="error">No user data in local storage</span>';
            }
        }
        
        function clearLocalStorage() {
            localStorage.removeItem('securehealth_user');
            showLocalStorage();
        }
        
        function testRedirect() {
            window.location.href = 'http://localhost:8081/patients';
        }
        
        // Show storage content on load
        showLocalStorage();
    </script>
</body>
</html>
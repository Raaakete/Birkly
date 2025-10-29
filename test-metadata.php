<!DOCTYPE html>
<html>
<head>
    <title>Test Metadata Endpoint</title>
</head>
<body>
    <h1>Test Metadata Endpoint</h1>
    
    <form id="testForm">
        <input type="url" id="urlInput" placeholder="Enter URL (e.g., https://trackunit.com/de/)" style="width: 400px; padding: 8px;">
        <button type="submit">Fetch Metadata</button>
    </form>
    
    <div id="result" style="margin-top: 20px; padding: 20px; border: 1px solid #ccc; background: #f9f9f9;"></div>
    
    <script>
        document.getElementById('testForm').addEventListener('submit', function(e) {
            e.preventDefault();
            
            const url = document.getElementById('urlInput').value;
            const resultDiv = document.getElementById('result');
            
            if (!url) {
                resultDiv.innerHTML = '<p style="color: red;">Please enter a URL</p>';
                return;
            }
            
            resultDiv.innerHTML = '<p>Fetching metadata...</p>';
            
            fetch('/api/fetch-metadata.php?url=' + encodeURIComponent(url))
                .then(response => response.json())
                .then(data => {
                    console.log('Metadata response:', data);
                    
                    if (data.success) {
                        resultDiv.innerHTML = `
                            <h3>✅ Success!</h3>
                            <p><strong>Title:</strong> ${data.data.title || 'N/A'}</p>
                            <p><strong>Description:</strong> ${data.data.description || 'N/A'}</p>
                            <p><strong>Image:</strong> ${data.data.image || 'N/A'}</p>
                            <p><strong>Favicon:</strong> ${data.data.favicon || 'N/A'}</p>
                        `;
                    } else {
                        resultDiv.innerHTML = `<p style="color: red;">❌ Error: ${data.error}</p>`;
                    }
                })
                .catch(error => {
                    console.error('Fetch error:', error);
                    resultDiv.innerHTML = `<p style="color: red;">❌ Network error: ${error.message}</p>`;
                });
        });
    </script>
</body>
</html>

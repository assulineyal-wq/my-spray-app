<!DOCTYPE html>
<html lang="he" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>מערכת ריסוס מסונכרנת</title>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/xlsx/0.18.5/xlsx.full.min.js"></script>
    <style>
        body { font-family: system-ui, sans-serif; background: #f4f7f6; padding: 15px; margin: 0; }
        .card { background: white; padding: 20px; border-radius: 15px; box-shadow: 0 4px 15px rgba(0,0,0,0.1); max-width: 600px; margin: auto; margin-bottom: 20px; }
        h3 { color: #2e7d32; border-bottom: 2px solid #e8f5e9; padding-bottom: 5px; margin-top: 0; }
        label { display: block; margin: 10px 0 5px; font-weight: bold; font-size: 14px; }
        input, select { width: 100%; padding: 12px; border: 1px solid #ccc; border-radius: 8px; box-sizing: border-box; font-size: 16px; }
        .chem-item { background: #f9f9f9; padding: 10px; border-radius: 10px; margin-bottom: 10px; border-right: 4px solid #2e7d32; position: relative; }
        .btn { width: 100%; padding: 15px; border: none; border-radius: 8px; cursor: pointer; font-weight: bold; font-size: 16px; margin-top: 10px; }
        .btn-add { background: #e8f5e9; color: #2e7d32; border: 1px dashed #2e7d32; }
        .btn-calc { background: #2e7d32; color: white; }
        #syncStatus { font-size: 12px; text-align: center; color: #666; margin-bottom: 10px; }
        table { width: 100%; border-collapse: collapse; margin-top: 10px; font-size: 12px; }
        th, td { border: 1px solid #ddd; padding: 8px; text-align: right; }
        th { background: #2e7d32; color: white; }
    </style>
</head>
<body>

<div id="syncStatus">מתחבר לשרת...</div>

<div class="card">
    <h3>1. פרטי ריסוס</h3>
    <label>תאריך:</label>
    <input type="date" id="date">
    <label>גידול:</label>
    <input type="text" id="crop" placeholder="שם הגידול">
    <label>שם חלקה:</label>
    <input type="text" id="plot" placeholder="שם החלקה">
</div>

<div class="card">
    <h3>2. הגדרות ומינונים</h3>
    <label>גודל מרסס (ליטר):</label>
    <input type="number" id="tankSize">
    <label>נפח תרסיס לדונם (ליטר):</label>
    <input type="number" id="waterPerDunam">
    
    <div id="chemicalsContainer"></div>
    <button class="btn btn-add" onclick="addChemical()">+ הוסף חומר נוסף</button>
</div>

<div class="card">
    <h3>3. ביצוע</h3>
    <button class="btn btn-calc" onclick="processAll()">חשב ושמור ביומן</button>
    <div id="results" style="display:none; background:#fffde7; padding:15px; border-radius:10px; margin-top:15px;"></div>
    
    <h3 style="margin-top:20px;">יומן ריסוסים (מסונכרן)</h3>
    <div style="overflow-x:auto;">
        <table id="logTable">
            <thead><tr><th>תאריך</th><th>גידול/חלקה</th><th>חומרים</th></tr></thead>
            <tbody id="logBody"></tbody>
        </table>
    </div>
    <button class="btn" style="background:#0277bd; color:white;" onclick="exportToExcel()">יצוא לאקסל</button>
</div>

<script>
    let sprayLog = [];
    document.getElementById('date').valueAsDate = new Date();

    // טעינה ראשונית מהשרת
    async function loadFromServer() {
        try {
            const res = await fetch('api.php');
            sprayLog = await res.json();
            updateLogTable();
            document.getElementById('syncStatus').innerText = "מחובר ומסונכרן לשרת";
        } catch (e) {
            document.getElementById('syncStatus').innerText = "שגיאת סנכרון - עובד מקומית";
        }
    }

    async function saveToServer() {
        document.getElementById('syncStatus').innerText = "שומר לענן...";
        try {
            await fetch('api.php', {
                method: 'POST',
                body: JSON.stringify(sprayLog)
            });
            document.getElementById('syncStatus').innerText = "הנתונים נשמרו בענן";
        } catch (e) {
            alert("שגיאה בשמירה לשרת");
        }
    }

    function addChemical() {
        const div = document.createElement('div');
        div.className = 'chem-item';
        div.innerHTML = `
            <input type="text" class="chem-name" placeholder="שם חומר">
            <select class="calc-type">
                <option value="percent">אחוז (%)</option>
                <option value="gramLiter">גרם/סמ"ק לליטר</option>
            </select>
            <input type="number" step="0.001" class="chem-value" placeholder="מינון">
        `;
        document.getElementById('chemicalsContainer').appendChild(div);
    }

    function processAll() {
        const tank = parseFloat(document.getElementById('tankSize').value);
        if(!tank) return alert("מלא נפח מרסס");

        const names = document.getElementsByClassName('chem-name');
        const types = document.getElementsByClassName('calc-type');
        const values = document.getElementsByClassName('chem-value');
        
        let materials = [];
        for(let i=0; i<names.length; i++) {
            let val = parseFloat(values[i].value);
            if(val) {
                let amount = (types[i].value === 'percent') ? (val/100)*tank*1000 : val*tank;
                materials.push(`${names[i].value || 'חומר'}: ${amount.toFixed(1)}`);
            }
        }

        const entry = {
            date: document.getElementById('date').value,
            info: `${document.getElementById('crop').value} (${document.getElementById('plot').value})`,
            materials: materials.join(', ')
        };

        sprayLog.unshift(entry);
        updateLogTable();
        saveToServer();
    }

    function updateLogTable() {
        const body = document.getElementById('logBody');
        body.innerHTML = sprayLog.map(e => `<tr><td>${e.date}</td><td>${e.info}</td><td>${e.materials}</td></tr>`).join('');
    }

    function exportToExcel() {
        const ws = XLSX.utils.json_to_sheet(sprayLog);
        const wb = XLSX.utils.book_new();
        XLSX.utils.book_append_sheet(wb, ws, "Log");
        XLSX.writeFile(wb, "SprayLog.xlsx");
    }

    window.onload = () => { addChemical(); loadFromServer(); };
</script>
</body>
</html>
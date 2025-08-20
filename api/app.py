from flask import Flask, request, jsonify, send_from_directory
import mysql.connector
from datetime import datetime

app = Flask(__name__)

def get_db_connection():
    return mysql.connector.connect(
        host='168.231.68.229',
        port=3306,
        user='workbench_user',
        password='Mysql2025#',
        database='cloude_apcuadre',
        charset='utf8mb4'
    )

@app.route('/api/report/income', methods=['GET'])
def income_report():
    team_id = request.args.get('team_id')
    start_date = request.args.get('start_date')
    end_date = request.args.get('end_date')

    conn = get_db_connection()
    cursor = conn.cursor(dictionary=True)

    query = """
    SELECT
        i.income_date,
        i.invoice_number,
        c.name AS contractor_name,
        il.quantity,
        il.unit_price,
        il.total_amount,
        jt.name AS job_type
    FROM incomes i
    JOIN income_contractors ic ON i.id = ic.income_id
    JOIN contractors c ON ic.contractor_id = c.id
    JOIN income_lines il ON i.id = il.income_id
    JOIN job_types jt ON il.job_types_id = jt.id
    WHERE i.team_id = %s AND i.income_date BETWEEN %s AND %s
    ORDER BY i.income_date, c.name
    """
    cursor.execute(query, (team_id, start_date, end_date))
    results = cursor.fetchall()
    conn.close()

    # Agrupar por día
    report = {}
    for row in results:
        day = row['income_date'].strftime('%Y-%m-%d')
        if day not in report:
            report[day] = []
        report[day].append({
            'invoice_number': row['invoice_number'],
            'contractor': row['contractor_name'],
            'job_type': row['job_type'],
            'unit': row['quantity'],
            'price': row['unit_price'],
            'total': row['total_amount']
        })

    return jsonify(report)


# Endpoint para obtener equipos
@app.route('/api/teams', methods=['GET'])
def get_teams():
    conn = get_db_connection()
    cursor = conn.cursor(dictionary=True)
    cursor.execute("SELECT id, name FROM teams WHERE active = 1")
    teams = cursor.fetchall()
    conn.close()
    return jsonify(teams)

# Servir archivos estáticos para la UI
@app.route('/')
def serve_index():
    return send_from_directory('static', 'index.html')

@app.route('/static/<path:path>')
def serve_static(path):
    return send_from_directory('static', path)

if __name__ == '__main__':
    app.run(debug=True)

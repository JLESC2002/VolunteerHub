from flask import Flask, request, jsonify, session, make_response
from flask_mysqldb import MySQL
from flask_cors import CORS
import bcrypt
import secrets

app = Flask(__name__)

# Enable CORS for all routes
CORS(app, resources={r"/*": {"origins": "*"}}, supports_credentials=True)

# MySQL Configuration
app.config['MYSQL_HOST'] = 'localhost'
app.config['MYSQL_USER'] = 'root'
app.config['MYSQL_PASSWORD'] = ''
app.config['MYSQL_DB'] = 'volunteerhub'
app.config['MYSQL_CURSORCLASS'] = 'DictCursor'

mysql = MySQL(app)

app.config['SECRET_KEY'] = 'your_secret_key'
app.config['SESSION_TYPE'] = 'filesystem'


def authenticate_user(email, password, role):
    """Authenticate user and return session token if valid"""
    cursor = mysql.connection.cursor()
    cursor.execute("SELECT id, name, password FROM users WHERE email = %s AND role = %s", (email, role))
    user = cursor.fetchone()
    cursor.close()

    if user and bcrypt.checkpw(password.encode('utf-8'), user['password'].encode('utf-8')):
        session_token = secrets.token_hex(16)  # Generate a secure session token

        # Store session token in MySQL
        cursor = mysql.connection.cursor()
        cursor.execute("UPDATE users SET session_token = %s WHERE id = %s", (session_token, user['id']))
        mysql.connection.commit()
        cursor.close()

        return user, session_token
    return None, None




@app.route('/login', methods=['POST'])
def login():
    try:
        data = request.json
        email = data.get('email')
        password = data.get('password')
        role = data.get('role')

        if not email or not password or role not in ['Admin', 'Volunteer']:
            return jsonify({'status': 'error', 'message': 'Invalid credentials'}), 400

        user, session_token = authenticate_user(email, password, role)
        if user:
            session['user_id'] = user['id']
            session['user_name'] = user['name']
            session['user_role'] = role
            session['session_token'] = session_token

            print(f"Generated session_token: {session_token}")  # DEBUG PRINT

            response = jsonify({
                'status': 'success',
                'message': f'{role} login successful',
                'redirect': f'../{role.lower()}/{role.lower()}_dashboard.php',
                'session_token': session_token  # MAKE SURE THIS IS SENT!
            })
            response.headers.add("Access-Control-Allow-Origin", "*")
            response.headers.add("Access-Control-Allow-Credentials", "true")
            response.headers.add("Access-Control-Allow-Methods", "GET, POST, OPTIONS")
            response.headers.add("Access-Control-Allow-Headers", "Content-Type, Authorization")

            response.set_cookie('session_token', session_token, httponly=False, samesite='Lax')

            return response

        return jsonify({'status': 'error', 'message': 'Incorrect email or password'}), 401

    except Exception as e:
        print("Error:", str(e))  # Debug error output
        return jsonify({'status': 'error', 'message': 'Internal Server Error'}), 500



# Handle preflight CORS requests
@app.route('/login', methods=['OPTIONS'])
def handle_preflight():
    response = jsonify({'message': 'CORS preflight successful'})
    response.headers.add("Access-Control-Allow-Origin", "*")
    response.headers.add("Access-Control-Allow-Credentials", "true")
    response.headers.add("Access-Control-Allow-Methods", "GET, POST, OPTIONS")
    response.headers.add("Access-Control-Allow-Headers", "Content-Type, Authorization")
    return response


if __name__ == '__main__':
    app.run(debug=True)
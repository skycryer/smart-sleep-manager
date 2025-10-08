#!/usr/bin/env python3
import http.server
import socketserver
import json
from datetime import datetime
from urllib.parse import parse_qs
import sys
import os

class TestHandler(http.server.SimpleHTTPRequestHandler):
    def do_GET(self):
        if self.path.endswith('/test_basic.php'):
            self.send_response(200)
            self.send_header('Content-type', 'application/json')
            self.end_headers()
            
            response = {
                'success': True,
                'message': 'PHP is working correctly (simulated)',
                'timestamp': datetime.now().strftime('%Y-%m-%d %H:%M:%S'),
                'php_version': '8.1.0 (simulated)'
            }
            self.wfile.write(json.dumps(response).encode())
            
        elif self.path.endswith('/test_telegram.php'):
            self.send_response(200)
            self.send_header('Content-type', 'application/json')
            self.end_headers()
            
            # Simulate successful Telegram response
            response = {
                'success': True,
                'message': 'Test message sent successfully (simulated)'
            }
            self.wfile.write(json.dumps(response).encode())
            
        else:
            super().do_GET()
    
    def do_POST(self):
        if self.path.endswith('/test_telegram.php'):
            content_length = int(self.headers['Content-Length'])
            post_data = self.rfile.read(content_length).decode('utf-8')
            parsed_data = parse_qs(post_data)
            
            self.send_response(200)
            self.send_header('Content-type', 'application/json')
            self.end_headers()
            
            bot_token = parsed_data.get('bot_token', [''])[0]
            chat_id = parsed_data.get('chat_id', [''])[0]
            
            if not bot_token or not chat_id:
                response = {
                    'success': False,
                    'error': 'Bot token and chat ID are required'
                }
            else:
                response = {
                    'success': True,
                    'message': f'Telegram test successful (simulated) - Token: {bot_token[:10]}..., Chat: {chat_id}'
                }
            
            self.wfile.write(json.dumps(response).encode())
        else:
            super().do_POST()

if __name__ == "__main__":
    PORT = 8000
    print(f"Starting test server on http://localhost:{PORT}")
    print("Testing URLs:")
    print(f"  - http://localhost:{PORT}/include/test_basic.php")
    print(f"  - http://localhost:{PORT}/include/test_telegram.php")
    print(f"  - http://localhost:{PORT}/SmartSleepSettings.page")
    print("\nPress Ctrl+C to stop")
    
    with socketserver.TCPServer(("", PORT), TestHandler) as httpd:
        try:
            httpd.serve_forever()
        except KeyboardInterrupt:
            print("\nServer stopped")
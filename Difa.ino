#include <ESP8266WiFi.h>
#include <ESP8266HTTPClient.h>
#include <ArduinoJson.h>

// Konfigurasi WiFi
const char* ssid = "Difa";
const char* password = "12345678";

// Konfigurasi Server (ganti dengan IP server Anda)
const char* serverUrl = "http://192.168.43.119/iot_abc/api.php";

// Pin Sensor
const int pinHujanDO = 5;   // D1
const int pinLDRDO = 0;     // D3
const int pinLED = 2;       // D4

// Variabel kontrol manual
bool manualMode = false;
bool manualState = LOW;

void setup() {
  Serial.begin(9600);
  
  // Inisialisasi Pin
  pinMode(pinHujanDO, INPUT);
  pinMode(pinLDRDO, INPUT);
  pinMode(pinLED, OUTPUT);
  digitalWrite(pinLED, LOW);

  // Koneksi WiFi
  WiFi.begin(ssid, password);
  Serial.print("Connecting to WiFi");
  while (WiFi.status() != WL_CONNECTED) {
    delay(500);
    Serial.print(".");
  }
  Serial.print("\nConnected! IP: ");
  Serial.println(WiFi.localIP());
}

void loop() {
  // Baca sensor
  int statusHujan = digitalRead(pinHujanDO);
  int statusLDR = digitalRead(pinLDRDO);
  
  // Kirim data ke server setiap 3 detik
  if (WiFi.status() == WL_CONNECTED) {
    sendSensorData(statusHujan, statusLDR);
    getLampCommand();
  }

  // Kontrol lampu
  if (manualMode) {
    digitalWrite(pinLED, manualState);
  } else {
    // Mode otomatis
    digitalWrite(pinLED, (statusHujan == LOW || statusLDR == HIGH) ? HIGH : LOW);
  }

  delay(3000);
}

void sendSensorData(int hujan, int ldr) {
  HTTPClient http;
  WiFiClient client;
  
  http.begin(client, String(serverUrl) + "?action=save_sensor");
  http.addHeader("Content-Type", "application/json");

  // Buat payload JSON
  DynamicJsonDocument doc(128);
  doc["hujan"] = (hujan == LOW) ? "Ya" : "Tidak";
  doc["cahaya"] = (ldr == HIGH) ? "Gelap" : "Terang";
  
  String payload;
  serializeJson(doc, payload);

  int httpCode = http.POST(payload);
  
  if (httpCode > 0) {
    Serial.printf("Data sent! Code: %d\n", httpCode);
  } else {
    Serial.printf("HTTP error: %s\n", http.errorToString(httpCode).c_str());
  }
  
  http.end();
}

void getLampCommand() {
  HTTPClient http;
  WiFiClient client;
  
  http.begin(client, String(serverUrl) + "?action=get_lamp");
  int httpCode = http.GET();

  if (httpCode == HTTP_CODE_OK) {
    String response = http.getString();
    DynamicJsonDocument doc(64);
    deserializeJson(doc, response);
    
    manualMode = doc["manual_mode"];
    manualState = doc["lamp_status"] ? HIGH : LOW;
  }
  
  http.end();
}
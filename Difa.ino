#include <ESP8266WiFi.h>
#include <ESP8266HTTPClient.h>
#include <ArduinoJson.h>

// Konfigurasi WiFi
const char* ssid = "DIRECT-NS-Hotspot";
const char* password = "87654321";
const char* serverUrl = "http://192.168.49.109/iot_abc/api.php";

// Pin Sensor
const int pinHujanDO = 5;   // D1
const int pinLDRDO = 0;     // D3 (BUTTON FLASH)
const int pinLED = 2;       // D4 (LED Built-in)

// Variabel kontrol
bool manualMode = false;
bool manualState = LOW;

void setup() {
  Serial.begin(115200);
  
  // Inisialisasi Pin
  pinMode(pinHujanDO, INPUT_PULLUP);  // Aktifkan pull-up internal
  pinMode(pinLDRDO, INPUT_PULLUP);    // Aktifkan pull-up internal
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

  // Tes awal sensor
  testSensors();
}

void testSensors() {
  Serial.println("\nTesting Sensors...");
  Serial.println("Cover LDR to test, then press any key...");
  while(!Serial.available()) {
    int ldr = digitalRead(pinLDRDO);
    int rain = digitalRead(pinHujanDO);
    Serial.printf("LDR: %d (0=Gelap), Rain: %d (0=Hujan)\n", ldr, rain);
    delay(500);
  }
  while(Serial.available()) Serial.read(); // Clear buffer
}

void loop() {
  // Baca sensor dengan debounce
  int statusHujan = readSensorDebounced(pinHujanDO, 50);
  int statusLDR = readSensorDebounced(pinLDRDO, 50);
  
  // Debug output
  Serial.printf("Hujan: %s | Cahaya: %s | Mode: %s | Lamp: %s\n",
    statusHujan == LOW ? "Ya" : "Tidak",
    statusLDR == LOW ? "Gelap" : "Terang",  // Diubah logikanya
    manualMode ? "Manual" : "Auto",
    digitalRead(pinLED) ? "ON" : "OFF"
  );

  // Kirim data ke server jika WiFi tersambung
  if (WiFi.status() == WL_CONNECTED) {
    sendSensorData(statusHujan, statusLDR);
    getLampCommand();
  } else {
    Serial.println("WiFi disconnected! Attempting to reconnect...");
    WiFi.reconnect();
  }

  // Kontrol lampu
  if (manualMode) {
    digitalWrite(pinLED, manualState);
  } else {
    // Mode otomatis: Nyala saat hujan ATAU gelap
    digitalWrite(pinLED, (statusHujan == LOW || statusLDR == LOW) ? HIGH : LOW);
  }

  delay(1000); // Delay utama
}

int readSensorDebounced(int pin, unsigned long debounceDelay) {
  static unsigned long lastTime = 0;
  static int lastStableState = HIGH;
  int currentState = digitalRead(pin);
  
  if (millis() - lastTime > debounceDelay) {
    if (currentState != lastStableState) {
      lastStableState = currentState;
    }
    lastTime = millis();
  }
  
  return lastStableState;
}

void sendSensorData(int hujan, int ldr) {
  HTTPClient http;
  WiFiClient client;
  
  http.begin(client, String(serverUrl) + "?action=save_sensor");
  http.addHeader("Content-Type", "application/json");

  DynamicJsonDocument doc(128);
  doc["hujan"] = (hujan == LOW) ? "Ya" : "Tidak";
  doc["cahaya"] = (ldr == LOW) ? "Gelap" : "Terang"; // Diubah logikanya
  
  String payload;
  serializeJson(doc, payload);

  int httpCode = http.POST(payload);
  
  if (httpCode > 0) {
    Serial.printf("[HTTP] POST data sent: %d\n", httpCode);
  } else {
    Serial.printf("[HTTP] POST failed: %s\n", http.errorToString(httpCode).c_str());
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
    
    Serial.printf("Lamp control updated - Manual: %s, State: %s\n",
      manualMode ? "Yes" : "No",
      manualState ? "ON" : "OFF"
    );
  } else {
    Serial.printf("[HTTP] GET failed: %s\n", http.errorToString(httpCode).c_str());
  }
  
  http.end();
}
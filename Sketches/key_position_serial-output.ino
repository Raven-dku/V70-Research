/** 
*  A simple test for Key Position and minimum possible delay for reading.
*  Was written specifically for Volvo V70 2001, but it seems it may be working on a lot of more models.
*  Super sucky code, reading by diagnostic messages is not a good idea.
*  Written by Tomas Rad (Raven) @ 2022-02-17
*/

#include <mcp_can.h>
#include <SPI.h>

#define CAN0_INT 2                              // Set INT to pin 2
MCP_CAN CAN0(10);                               // Set CS to pin 10

int messageDelay = 30; // 125kbps recommended is 30ms, fine tuning needed.
int keyPosition = 0;   // 0 - OFF, 1 - Pos 1, 2 - Ignition ON, 3 - Ignition START

unsigned char diagnosticMessages[1][8] = {
  {0xCD, 0x40, 0xA6, 0x1A, 0x04, 0x01, 0x00, 0x00},
};

byte data[1][8] = {
  {0xCD, 0x40, 0xA6, 0x1A, 0x04, 0x01, 0x00, 0x00},
};

void setup()
{
  // Initialize Serial connection if available.
  while (!Serial);
    Serial.begin(115200);
    Serial.setTimeout(10);

  // Initialize MCP2515 running at 16MHz with a baudrate of 500kb/s and the masks and filters disabled.
  if(CAN0.begin(MCP_ANY, CAN_125KBPS, MCP_16MHZ) == CAN_OK)
    Serial.println("MCP2515 Initialized Successfully!");
  else
    Serial.println("Error Initializing MCP2515...");

    CAN0.setMode(MCP_NORMAL);                     // Set operation mode to normal so the MCP2515 sends acks to received data.

    pinMode(CAN0_INT, INPUT);                            // Configuring pin for /INT input

    Serial.println("Setup Complete.");
}

void updateKeyPosition(unsigned char rx
)
{
  int value = rx;
  int kPos;
  
  switch(value) {
    case 28:
      kPos = 0;
      break;
    case 29:
      kPos = 1;
      break;
    case 30:
      kPos = 2;
      break;
    case 31:
      kPos = 3;
      break;
    default:
      kPos = 0;
      break;
  }

  if(kPos != keyPosition) {
    Serial.println("");
    Serial.print("Key position has changed!"); Serial.println(""); Serial.print("Old Value: Pos "); Serial.print(keyPosition); Serial.print(" | New Value: Pos "); Serial.print(kPos);
    keyPosition = kPos;
  }
}

void sendMessageLOW(long address, byte stmp[])
{
  byte sndStat = CAN0.sendMsgBuf(0x100, address, 8, stmp);
  if(sndStat == CAN_OK) {
    Serial.println("Message Sent Successfully!");
  } else {
    Serial.println("Error Sending Message...");
  }
}

long unsigned int rxId;
unsigned char len = 0;
unsigned char rxBuf[8];
char msgString[128];                        // Array to store serial string

void loop()
{
  if(!digitalRead(CAN0_INT))                         // If CAN0_INT pin is low, read receive buffer
  {
    CAN0.readMsgBuf(&rxId, &len, rxBuf);      // Read data: len = data length, buf = data byte(s)

   if(rxBuf[2] == 230 && rxBuf[3] == 26 && rxBuf[4] == 4) {
        updateKeyPosition(rxBuf[5]);
   }  
  }
    delay(30);
    sendMessageLOW(0x000FFFFE, data[0]);
}

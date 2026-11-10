<?php

$lines = <<<'NOTES'
#include <iostream> ~ Include standard console input and output for std::cin and std::cout.
#include <string> ~ Include the standard library for text stored in std::string.
#include <cmath> ~ Include mathematical functions such as square root, powers and rounding.
int main() { ~ Begin main(), the starting function of this C++ program.
return 0; ~ End this function and report successful completion to the operating system.
} ~ Close the current function, loop or conditional block.
std::cout << "Hello, CodeQuest!"; ~ Print the greeting text in the output console.
std::cout << "Build complete!"; ~ Print a message confirming that the program reached this statement.
std::cout << "Syntax checked"; ~ Print the text inside the quotation marks.
std::cout << "Hello, CodeQuest!\n"; ~ Print the greeting; \n moves the next output to a new line.
std::cout << "I am learning C++."; ~ Print a second sentence after the previous output.
int groups = 3; ~ Create an integer named groups with the initial value 3.
std::cout << groups * 4; ~ Multiply the number of groups by four and print the result.
int teams = 3; ~ Store the number of teams in an integer variable.
int devicesPerTeam = 4; ~ Store how many devices each team needs.
int total = teams * devicesPerTeam; ~ Multiply teams by devices per team and store the total.
std::cout << total; ~ Print the current value stored in total.
int amount = 0; ~ Initialize an integer variable to hold the input.
std::cin >> amount; ~ Read an integer from standard input into amount.
std::cout << amount + 2; ~ Add two to the input and print the sum.
int count = 4; ~ Create an integer named count with the initial value 4.
double average = 7.0 / 2; ~ Use floating-point division and store the result in a double.
char group = 'B'; ~ Store one character using single quotation marks.
bool ready = true; ~ Store a Boolean value representing a true condition.
auto extra = 2; ~ Let C++ infer the integer type from the initial value.
std::cout << count + extra << "\n" << average << "\n" << group << "\n" << ready; ~ Print the sum, average, character and Boolean on separate lines; true normally prints as 1.
int a = 7, b = 2; ~ Declare and initialize two integer variables on one line.
std::cout << a + b << std::endl << a - b << std::endl ~ Print addition and subtraction on separate lines; the expression continues below.
<< a * b << std::endl << a / b << std::endl << a % b; ~ Continue printing multiplication, integer division and remainder results.
std::string name = "Ada"; ~ Store a name as a standard C++ string.
std::cout << "Hello, " << name << "!"; ~ Print fixed greeting text together with the stored name.
std::cout << std::sqrt(49.0) << "\n"; ~ Calculate the square root of 49 and print it followed by a new line.
std::cout << std::pow(2.0, 3.0) << "\n"; ~ Calculate two raised to the third power and print it.
std::cout << std::ceil(2.2); ~ Round 2.2 upward to the next whole-number value and print it.
int score = 72; ~ Store a sample score for the comparison.
bool passed = score >= 60; ~ Compare the score with the threshold and store true or false.
std::cout << passed << "\n" << !passed; ~ Print passed and its logical opposite; Booleans normally print as 1 or 0.
int temperature = 32; ~ Store a temperature to test with an if statement.
if (temperature >= 30) { ~ Enter this block only when the temperature is at least 30.
std::cout << "Hot"; ~ Print the message for the true branch.
} else { ~ Close the true branch and begin the alternative branch when its condition is false.
std::cout << "Cool"; ~ Print the alternative message.
int choice = 2; ~ Store the menu choice to examine.
switch (choice) { ~ Select a matching case using the value of choice.
case 1: std::cout << "Read"; break; ~ For option 1, print Read and leave the switch with break.
case 2: std::cout << "Code"; break; ~ For option 2, print Code and leave the switch with break.
default: std::cout << "Choose 1 or 2"; ~ Print a fallback message if no case matches.
int number = 0; ~ Initialize the variable that will hold each input number.
int total = 0; ~ Start the running total at zero before adding values.
std::cin >> number; ~ Read the next integer, including a possible stop value.
while (number != -1) { ~ Repeat while the input is not the sentinel value -1.
total += number; ~ Add the current number to the running total.
for (int i = 5; i >= 1; i--) { ~ Count down from 5 through 1, subtracting one after each repetition.
std::cout << i << "\n"; ~ Print the current counter followed by a new line.
std::cout << "Launch!"; ~ Print the final message after the countdown ends.
for (int i = 1; i <= 6; i++) { ~ Start at 1 and increase the counter while it is at most 6.
if (i == 2) { continue; } ~ Skip the rest of this repetition when the counter is 2.
if (i == 5) { break; } ~ Leave the loop completely when the counter reaches 5.
int values[5] = {1,2,3,4,5}; ~ Create an array of five integers with the listed values.
for (int i = 0; i < 5; ++i) total += values[i]; ~ Visit indexes 0 through 4 and add every array element to total.
struct Supply { std::string label; int quantity; }; ~ Define a structure containing a text label and an integer quantity.
Supply item{"Markers", 4}; ~ Create a Supply object and initialize its two members.
std::cout << item.label << "\n" << item.quantity; ~ Access the object's members with a dot and print them on separate lines.
enum class Stage { Read, Code, Review }; ~ Define a scoped enumeration with three named stages.
Stage current = Stage::Code; ~ Store Code as the current stage using the enumeration's scope.
std::cout << (current == Stage::Code); ~ Compare the stage with Code and print the Boolean result.
int& alias = count; ~ Create a reference to the same integer as count.
alias += 3; ~ Add three through the reference, changing the original integer too.
std::cout << count << "\n" << (&alias == &count); ~ Print count and whether alias and count have the same memory address.
int* pointer = &count; ~ Store the address of count in an integer pointer.
*pointer = 9; ~ Dereference the pointer and change the integer at that address to 9.
std::cout << count << "\n" << *pointer; ~ Print count and the value reached through the pointer.
int* value = new int(5); ~ Allocate one integer initialized to 5 and retain its address for later deletion.
std::cout << *value; ~ Dereference the allocated integer and print its value.
delete value; ~ Release the integer allocated with new; do not access that allocation again.
value = nullptr; ~ Clear the pointer so it no longer holds the deleted allocation's address.
int square(int number) { ~ Define a function that accepts an integer and returns an integer.
return number * number; ~ Return the argument multiplied by itself to the caller.
std::cout << square(5); ~ Call square with 5 and print the returned result.
int supplies(int teams, int perTeam = 4) { ~ Define two parameters; perTeam defaults to 4 when omitted by the caller.
return teams * perTeam; ~ Return the total supplies calculated from the parameters.
std::cout << supplies(3) << "\n" << supplies(3, 2); ~ Print one call using the default parameter and another using an explicit value of 2.
void setup() { ~ Begin setup(), which runs once when the Arduino starts or resets.
void loop() { ~ Begin loop(), which Arduino repeats after setup() finishes.
void loop() {} ~ Leave the repeating loop empty; there are no repeated actions in this sketch.
Serial.begin(9600); ~ Start serial communication at 9600 bits per second; match this in Serial Monitor.
Serial.println("Sense -> Decide -> Act -> Check again"); ~ Print the robotics cycle and finish the Serial Monitor line.
Serial.println("Power off -> Inspect -> Teacher check -> Power on"); ~ Print the circuit-check sequence before practical work.
pinMode(8, OUTPUT); ~ Configure pin 8 as an output; use a suitable circuit with current limiting.
digitalWrite(8, HIGH); ~ Set pin 8 HIGH; the connected circuit determines the output effect.
pinMode(LED_BUILTIN, OUTPUT); ~ Configure the board's built-in LED pin as an output.
digitalWrite(LED_BUILTIN, HIGH); ~ Set the built-in LED pin HIGH to turn on the Uno R3 LED.
Serial.println("Uno R3 ready"); ~ Print the board's readiness message.
Serial.println("Setup runs once"); ~ Print this message during the one-time setup stage.
Serial.println("Loop is running"); ~ Print a message whenever this repeating loop reaches the line.
delay(1000); ~ Pause the sketch for 1000 milliseconds, which is one second.
const byte ledPin = LED_BUILTIN; ~ Give the built-in LED pin a constant name.
void setup() { pinMode(ledPin, OUTPUT); } ~ Configure the named LED pin as an output during setup.
digitalWrite(ledPin, HIGH); ~ Set the LED pin HIGH to switch on the Uno LED circuit.
delay(250); ~ Pause for 250 milliseconds, one quarter of a second.
digitalWrite(ledPin, LOW); ~ Set the LED pin LOW to switch off the Uno LED circuit.
delay(750); ~ Pause for 750 milliseconds before repeating.
int count = 0; ~ Start the flash counter at zero.
void flashOnce(unsigned long onMs) { ~ Define a helper receiving an LED on-time in milliseconds.
digitalWrite(ledPin, HIGH); delay(onMs); ~ Switch the LED on and wait for the supplied on-time.
digitalWrite(ledPin, LOW); delay(300); ~ Switch the LED off and leave a 300-millisecond gap.
void setup() { Serial.begin(9600); pinMode(ledPin, OUTPUT); } ~ Start serial communication and configure the LED output once.
count++; Serial.println(count); ~ Increase the counter and print its updated value.
if (count <= 3) { flashOnce(100); } ~ Flash for 100 milliseconds when the counter is at most 3.
else { flashOnce(500); count = 0; } ~ Otherwise flash for 500 milliseconds and reset the counter.
const byte buttonPin = 2; ~ Name pin 2 as the button input.
pinMode(buttonPin, INPUT_PULLUP); ~ Enable the internal pull-up; a button wired to GND reads LOW when pressed.
bool pressed = digitalRead(buttonPin) == LOW; ~ Read whether the active-low button is pressed.
digitalWrite(LED_BUILTIN, pressed ? HIGH : LOW); ~ Turn on the built-in LED when pressed and turn it off otherwise.
Serial.println(pressed ? "Pressed" : "Released"); ~ Print the message matching the button state.
delay(50); ~ Wait fifty milliseconds; this delay alone is not a complete debounce algorithm.
const byte redPin=8, amberPin=9, greenPin=10; ~ Assign pins 8, 9 and 10 to the traffic-light LEDs.
void phase(bool r, bool a, bool g, unsigned long waitMs) { ~ Define a helper receiving three light states and a waiting time.
digitalWrite(redPin,r); digitalWrite(amberPin,a); digitalWrite(greenPin,g); ~ Set all three LED outputs to the requested states.
delay(waitMs); ~ Hold this phase for the supplied milliseconds.
void setup() { pinMode(redPin,OUTPUT); pinMode(amberPin,OUTPUT); pinMode(greenPin,OUTPUT); } ~ Configure all three traffic-light pins as outputs.
void loop() { phase(HIGH,LOW,LOW,3000); phase(LOW,LOW,HIGH,3000); phase(LOW,HIGH,LOW,1000); } ~ Repeat red for three seconds, green for three seconds and amber for one second.
void setup() { Serial.begin(9600); } ~ Start serial communication once at 9600 bits per second.
int raw = analogRead(A0); ~ Read A0 as a number from 0 to 1023 on the Uno R3.
float volts = raw * 5.0 / 1023.0; ~ Estimate voltage assuming a 5-volt reference; the actual reference may differ.
Serial.print("ADC="); Serial.print(raw); ~ Print the ADC label and raw reading without ending the line.
Serial.print(" estimated V="); Serial.println(volts,2); ~ Print the voltage estimate with two decimal places and end the line.
const byte ledPin=5; ~ Name PWM-capable pin 5 as the LED output.
void setup() { pinMode(ledPin,OUTPUT); } ~ Configure the named LED pin as an output.
int raw=analogRead(A0); ~ Read analog input A0 using the Uno's 10-bit ADC.
int duty=constrain(raw/4,0,255); ~ Scale the reading into the PWM range and clamp it between 0 and 255.
analogWrite(ledPin,duty); ~ Output PWM with this duty value; PWM is not a true analog voltage output.
delay(10); ~ Wait ten milliseconds before the next reading.
const byte redPin=3, greenPin=5, bluePin=6; ~ Assign three PWM-capable pins to the RGB LED channels.
void color(byte r, byte g, byte b) { analogWrite(redPin,r); analogWrite(greenPin,g); analogWrite(bluePin,b); } ~ Define a helper setting three PWM channels for a common-cathode RGB LED circuit.
void setup() { pinMode(redPin,OUTPUT); pinMode(greenPin,OUTPUT); pinMode(bluePin,OUTPUT); } ~ Configure all three RGB channel pins as outputs.
void loop() { color(255,0,0); delay(1000); color(255,255,0); delay(1000); color(0,0,255); delay(1000); color(0,0,0); delay(1000); } ~ Cycle through red, yellow, blue and off, holding each state for one second.
void setup() { pinMode(8,OUTPUT); Serial.begin(9600); } ~ Configure output pin 8 and start serial reporting.
int lightValue=analogRead(A0); ~ Read the light-sensor voltage divider connected to A0.
bool dark=lightValue<400; ~ Classify readings below 400 as dark; calibrate this threshold for the circuit.
digitalWrite(8,dark?HIGH:LOW); ~ Set output 8 HIGH in darkness and LOW otherwise.
Serial.print(lightValue); Serial.println(dark?" DARK: light ON":" BRIGHT: light OFF"); ~ Print the sensor reading and the matching control decision.
delay(200); ~ Wait 200 milliseconds before reading again.
const byte soundPin=8; ~ Name pin 8 as the passive-buzzer signal output.
void setup() { pinMode(soundPin,OUTPUT); } ~ Configure the buzzer signal pin as an output.
void loop() { tone(soundPin,1000,150); delay(250); tone(soundPin,1500,150); delay(250); noTone(soundPin); delay(2000); } ~ Play 1000-Hz and 1500-Hz tones for 150 milliseconds each, then stop and pause.
#include <Servo.h> ~ Include the library for hobby-servo signal control.
Servo arm; ~ Create a servo-control object named arm.
void setup() { arm.attach(9); arm.write(90); delay(1000); } ~ Attach the signal to pin 9, request 90 degrees and wait one second.
void loop() { arm.write(20); delay(1000); arm.write(90); delay(1000); arm.write(160); delay(1000); } ~ Request three servo angles with one-second pauses; inspect the mechanism's safe range first.
const byte triggerPin=7, echoPin=8; ~ Assign the ultrasonic trigger output and echo input pins.
void setup() { pinMode(triggerPin,OUTPUT); pinMode(echoPin,INPUT); Serial.begin(9600); } ~ Configure the ultrasonic sensor pins and start serial reporting.
digitalWrite(triggerPin,LOW); delayMicroseconds(2); ~ Hold the trigger LOW briefly before starting a measurement.
digitalWrite(triggerPin,HIGH); delayMicroseconds(10); digitalWrite(triggerPin,LOW); ~ Send a ten-microsecond trigger pulse and return the pin LOW.
unsigned long duration=pulseIn(echoPin,HIGH,30000UL); ~ Measure the echo HIGH pulse in microseconds with a 30000-microsecond timeout.
if(duration==0) { Serial.println("INVALID: no echo"); } ~ Report an invalid reading when pulseIn times out with zero.
else { Serial.print(duration*0.0343/2.0); Serial.println(" cm"); } ~ Convert sound travel time to centimeters, dividing by two for the return journey.
delay(100); ~ Pause 100 milliseconds between cycles.
float volts=raw*5.0/1023.0; ~ Estimate the sensor voltage using a nominal 5-volt ADC reference.
float temperatureC=(volts-0.5)*100.0; ~ Convert a TMP36 voltage estimate to Celsius using its 0.5-volt offset.
Serial.print("Estimated temperature: "); Serial.print(temperatureC,1); Serial.println(" C"); ~ Print the estimated temperature with one decimal place and its unit.
#include <LiquidCrystal.h> ~ Include the library for a compatible parallel character LCD.
LiquidCrystal lcd(12,11,5,4,3,2); ~ Map the LCD RS, enable and four data lines to the listed pins.
lcd.begin(16,2); ~ Initialize the LCD with sixteen columns and two rows.
lcd.setCursor(0,0); lcd.print("Grade 9 Robotics"); ~ Move to column zero of the first row and print its text.
lcd.setCursor(0,1); lcd.print("Ready to learn"); ~ Move to column zero of the second row and print its text.
const byte rows[4]={2,3,4,5}, cols[4]={6,7,8,9}; ~ Store the keypad row and column connections in arrays.
const char keys[4][4]={{'1','2','3','A'},{'4','5','6','B'},{'7','8','9','C'},{'*','0','#','D'}}; ~ Map each keypad row-and-column position to its character.
for(byte i=0;i<4;i++) { pinMode(rows[i],INPUT); digitalWrite(rows[i],LOW); pinMode(cols[i],INPUT_PULLUP); } ~ Initialize inactive rows as inputs and enable each column's pull-up.
for(byte r=0;r<4;r++) { ~ Scan the four rows one at a time.
pinMode(rows[r],OUTPUT); digitalWrite(rows[r],LOW); delayMicroseconds(10); ~ Activate this row LOW and allow a short settling time.
for(byte c=0;c<4;c++) { if(digitalRead(cols[c])==LOW) { Serial.println(keys[r][c]); } } ~ Read each column and print the pressed key found in this row.
pinMode(rows[r],INPUT); ~ Release the scanned row before activating another row.
void setup() { Serial.begin(9600); pinMode(2,INPUT_PULLUP); } ~ Start serial reporting and enable the joystick button's pull-up.
int x=analogRead(A0), y=analogRead(A1); ~ Read the joystick's horizontal and vertical axes.
Serial.print("X="); Serial.print(x); Serial.print(" Y="); Serial.print(y); ~ Print both raw axis readings on the same line.
Serial.print(" "); ~ Add a space before the direction message.
if(x<450) { Serial.print("LEFT"); } else if(x>570) { Serial.print("RIGHT"); } else { Serial.print("CENTER"); } ~ Classify the horizontal reading using a center dead zone; calibrate these thresholds.
Serial.println(digitalRead(2)==LOW?" BUTTON":" RELEASED"); delay(100); ~ Print the active-low button's state and wait 100 milliseconds.
const byte motionPin=2; ~ Name pin 2 as the PIR motion-sensor input.
Serial.begin(9600); pinMode(motionPin,INPUT); pinMode(LED_BUILTIN,OUTPUT); ~ Start reporting and configure the motion input and LED output.
Serial.println("WARMING UP: check your module settling time"); delay(60000); ~ Announce warm-up and wait sixty seconds; confirm the module's actual settling requirement.
bool event=digitalRead(motionPin)==HIGH; ~ Read whether the PIR module currently reports HIGH.
digitalWrite(LED_BUILTIN,event?HIGH:LOW); ~ Mirror the motion event on the built-in LED.
Serial.println(event?"Motion event":"No current event"); delay(200); ~ Print the motion state and wait 200 milliseconds.
const byte enablePin=5, in1=7, in2=8; ~ Assign a motor driver's PWM enable and direction pins; never drive a motor directly from GPIO.
void stopMotor() { analogWrite(enablePin,0); digitalWrite(in1,LOW); digitalWrite(in2,LOW); } ~ Define a helper disabling PWM and setting both direction inputs LOW.
void setup() { pinMode(enablePin,OUTPUT); pinMode(in1,OUTPUT); pinMode(in2,OUTPUT); stopMotor(); } ~ Configure the driver control outputs and start with the motor stopped.
stopMotor(); delay(2000); ~ Stop the motor and wait two seconds.
digitalWrite(in1,HIGH); digitalWrite(in2,LOW); analogWrite(enablePin,100); delay(2000); ~ Select one driver direction and PWM value 100 for two seconds; verify wiring first.
stopMotor(); delay(3000); ~ Stop the motor and wait three seconds before repeating.
const byte buttonPin=2; ~ Name pin 2 as the active-low button input.
unsigned long previous=0; ~ Store the last LED-change time as an unsigned millisecond count.
bool ledOn=false; ~ Start the stored LED state as off.
void setup() { pinMode(LED_BUILTIN,OUTPUT); pinMode(buttonPin,INPUT_PULLUP); } ~ Configure the LED output and button input with an internal pull-up.
unsigned long now=millis(); ~ Read elapsed milliseconds since startup without pausing the sketch.
if(now-previous>=500UL) { previous=now; ledOn=!ledOn; } ~ After at least 500 milliseconds, remember the time and toggle the LED state.
if(digitalRead(buttonPin)==LOW) { digitalWrite(LED_BUILTIN,LOW); } ~ Force the LED off while the button is pressed.
else { digitalWrite(LED_BUILTIN,ledOn?HIGH:LOW); } ~ Otherwise display the stored blinking state.
const char* decideAction(float distanceCm) { ~ Define a helper returning a decision message based on distance.
if(distanceCm<0) { return "INVALID: safe stop"; } ~ Return a safe-stop message for an invalid negative distance.
if(distanceCm<15) { return "NEAR: stop"; } ~ Return the stop message for an obstacle nearer than fifteen centimeters.
return "FAR: ready"; ~ Return the clear-distance message after the earlier conditions did not match.
float cases[]={10,15,20,-1}; ~ Create four test distances, including a boundary and an invalid reading.
for(byte i=0;i<4;i++) { Serial.print(cases[i]); Serial.print(" cm -> "); Serial.println(decideAction(cases[i])); } ~ Print each test distance and the decision returned by the helper.
const byte trigPin=7, echoPin=8; ~ Assign trigger and echo pins for the capstone sensor.
float readDistanceCm() { ~ Define a helper returning distance in centimeters or -1 for an invalid reading.
digitalWrite(trigPin,LOW); delayMicroseconds(2); digitalWrite(trigPin,HIGH); delayMicroseconds(10); digitalWrite(trigPin,LOW); ~ Send a ten-microsecond trigger pulse after a short LOW period.
unsigned long us=pulseIn(echoPin,HIGH,30000UL); ~ Measure the echo pulse with a thirty-millisecond timeout.
return us==0?-1.0:us*0.0343/2.0; ~ Return -1 on timeout, or convert the round-trip sound time into centimeters.
void setup() { Serial.begin(9600); pinMode(trigPin,OUTPUT); pinMode(echoPin,INPUT); pinMode(LED_BUILTIN,OUTPUT); } ~ Start serial communication and configure sensor and warning LED pins.
float cm=readDistanceCm(); ~ Call the measurement helper and store its returned distance.
bool valid=cm>=0; bool warn=!valid||cm<15.0; ~ Validate the reading and request a warning for an invalid reading or nearby obstacle.
digitalWrite(LED_BUILTIN,warn?HIGH:LOW); ~ Turn the warning LED on when needed and off otherwise.
if(!valid) { Serial.println("INVALID: check sensor"); } ~ Ask the learner to check the sensor when the measurement is invalid.
else { Serial.print(cm); Serial.println(warn?" cm: NEAR":" cm: FAR"); } ~ Print the valid distance and its near-or-far classification.
NOTES;

$notes = [];
foreach (explode("\n", $lines) as $line) {
    [$source, $explanation] = explode(' ~ ', $line, 2);
    $notes[$source] = $explanation;
}

return $notes;

<?php

/** Original question bank. First five rows are checkpoints; next ten form the quiz.
 * Rows contain prompt | correct answer | distractor | distractor | optional distractor.
 */
return [
    'robotics' => <<<'QUESTIONS'
What does a sensor do in a robot? | Detects information from its surroundings | Stores the teacher's password | Replaces every motor
Which part produces physical movement? | An actuator | A comment | A variable name
Which sequence describes a feedback system? | Sense, decide, act, sense again | Act once and ignore changes | Print a password first
Is every automatic machine intelligent? | No, many follow fixed programmed rules | Yes, every machine learns by itself | Yes, if it has an LED
Why test a classroom robot with people safely away from moving parts? | Unexpected movement can cause injury | It makes the code private | It doubles the battery voltage
Which is a sensor in a distance-warning model? | An ultrasonic distance module | A plastic chassis | A wheel | A jumper label
Which is an output in a night-light system? | An LED turning on | Light reaching the sensor | A stored threshold | A code comment
What does the controller do between sensing and acting? | Applies programmed decision rules | Creates electricity from nothing | Guarantees perfect decisions | Replaces the power supply
A sensor reports an obstacle. Which response is appropriate for a model vehicle? | Stop and check the surroundings | Always accelerate | Ignore every sensor | Short the battery
What makes a system closed-loop? | Its measured result influences the next action | It uses a closed plastic box | Its code has no functions | It has no inputs
Which is a measurable project goal? | Warn when an object is closer than 15 cm | Make the best robot ever | Use many wires | Never measure anything
What is the best first design artifact? | A labeled input-process-output diagram | An unrelated game score | A password list | A finished enclosure before planning
Why repeat a sensor experiment? | To check consistency under different conditions | To make one success look universal | To avoid recording failures | To remove all uncertainty
What should a robot do when a required sensor reading is invalid? | Enter a planned safe state | Assume the path is clear | Randomly drive a motor | Disable all checks forever
Which statement correctly describes this course? | We build supervised low-voltage learning prototypes | Our prototypes replace certified safety devices | Every robot needs internet access | An Uno automatically learns without code
QUESTIONS,
    'kit-safety' => <<<'QUESTIONS'
What should you do before changing breadboard wires? | Disconnect power | Increase the supply voltage | Hold two power wires together
Why put a resistor in series with an ordinary LED? | To limit its current | To store a sketch | To turn it into a sensor
Which connection can cause a short circuit? | Connecting 5V directly to GND | Connecting a signal through a suitable resistor | Reading a disconnected diagram
What should you do if a component becomes hot? | Disconnect power and ask the teacher to inspect it | Touch it repeatedly | Add more voltage
Can an Uno I/O pin directly power a DC motor? | No, use a suitable driver and supply | Yes, every motor needs only one pin | Yes, if the sketch is short
Which item should you inspect before powering a circuit? | Polarity, pin labels and wire connections | Only the project title | Only the color of the USB cable | The leaderboard first
Why keep drinks away from the kit? | Spills can create shorts and damage equipment | Water improves USB speed | Moisture raises program memory | Drinks replace resistors
Which supply is appropriate for introductory LED work? | Teacher-approved low-voltage USB power | Household mains wired to the breadboard | An unknown high-voltage battery | Any supply with a matching color
Which practice protects a shared kit? | Inventory parts before and after use | Pull components out while powered | Bend every lead flat repeatedly | Force connectors into place
What does the stripe on a common diode identify? | The cathode end | The software version | The resistance value | The positive supply in every circuit
Why check a module's datasheet? | Similar-looking modules can have different pin arrangements | Every module has identical pins | Color always determines voltage | A photo replaces all specifications
Which is a safe response to an uncertain wire connection? | Leave power off and ask for a check | Guess and power it immediately | Hide the diagram | Remove the current-limiting resistor
Why avoid loose metal objects on a powered board? | They may bridge contacts and create a short | They improve signal accuracy | They add more flash memory | They make all inputs LOW
When using an external motor supply, what needs planning? | Driver ratings, common ground and backfeed prevention | Only the motor's sticker | A direct GPIO-to-motor connection | Joining every positive rail indiscriminately
Which evidence best supports a safe build? | A checked wiring diagram and documented inspection | A claim that it looks nice | A copied project name | One random successful upload
QUESTIONS,
    'breadboards' => <<<'QUESTIONS'
What joins the five holes in a typical breadboard terminal strip? | An internal conducting clip | A software setting | Five separate batteries
What normally separates the two terminal blocks? | The center channel | An Arduino variable | A USB driver
Are all long power rails connected from end to end? | No, some rails are split | Yes, on every breadboard | Only when the LED is green
What does a closed circuit provide? | A complete current path | A missing return connection | An unpowered sketch file
When should continuity be checked? | With the circuit unpowered | While shorting the supply | Only after removing every label
Why can an LED fail to light even if its sketch is correct? | Its circuit path may be open | The source has too many spaces | Its nickname is too short | All working LEDs require Wi-Fi
Where should the two leads of a resistor be placed to use it in series? | In different connected nodes along the current path | In the same five-hole strip | Both in an unused hole | Both on 5V only
What is a node? | A set of electrically connected points | A type of code comment | The Arduino IDE window | A stored quiz mark
Which LED path is correct for this lesson? | Digital output, resistor, LED, GND | 5V directly to GND | LED with both leads in one node | Resistor connected only to itself
Why use consistent wire colors? | They help people trace connections, though labels still matter | They determine voltage automatically | They eliminate polarity | They increase pin current limits
What can bridge a split power rail safely when the design requires it? | A correctly placed jumper checked with power off | A software delay | A longer variable name | A code comment
What does a floating input lack? | A defined electrical level | A loop function | A USB connector | A file extension
Which tool setting checks whether two unpowered points are connected? | Continuity | AC mains measurement on a live kit | Temperature only | Screen brightness
Why put a DIP component across the center channel? | Its opposite pin rows remain on separate nodes | All its pins then short together | Its code uploads faster | It no longer needs a supply
Which debugging method changes the fewest things at once? | Compare one connection at a time with the diagram | Move every wire simultaneously | Randomly change the supply | Remove every resistor
QUESTIONS,
    'uno-board' => <<<'QUESTIONS'
Which microcontroller is used by the classic Uno R3? | ATmega328P | A desktop graphics card | An ESP32 in every Uno
How many digital pins are numbered 0 through 13? | 14 | 13 | 6
Which labels identify the six analog inputs? | A0 through A5 | D20 through D25 | USB0 through USB5
What is LED_BUILTIN used for? | Referring to the board's built-in controllable LED | Increasing memory | Selecting a Wi-Fi network
What does pressing reset normally do? | Restarts the running sketch | Erases the IDE from the computer | Converts the board to an Uno R4
What is the Uno R3 operating logic voltage? | 5 V | 120 V | 48 V | 0 V only
Which pin provides a common electrical reference? | GND | RESET as a power output | AREF as a motor supply | TX as a battery terminal
Which group contains only Uno R3 PWM-capable pins? | 3, 5, 6, 9, 10, 11 | 2, 4, 7, 8, 12, 13 | A0, A1, A2 only | Every power pin
What do digital pins 0 and 1 also support? | Serial RX and TX | Unlimited motor power | Analog-only measurement | USB charging control
Why avoid using pins 0 and 1 for a beginner LED circuit while using Serial? | Connected devices can interfere with serial communication | Those pins are ground only | They are not connected to the board | They require mains voltage
Which statement distinguishes Uno R3 from Uno R4? | They use different processors and some capabilities differ | Their processors are always identical | R3 has built-in Wi-Fi by default | Every sketch has identical timing on both
Which connection is typically used for uploading to an Uno R3? | USB | HDMI | An LED lead | A breadboard rail
Which is a sensible external LED pin for first practice? | D8 with a series resistor and correct return path | 5V directly shorted to GND | RESET tied to an unknown supply | A bare motor on D8
What should be checked before attaching a sensor output? | Its signal voltage is compatible with the board | Only its packaging color | The number of comments in the sketch | Whether its name contains Arduino
Which I/O current figure appears in the Uno R3 operating specifications? | 20 mA per I/O pin | 2 A per I/O pin | 20 A per pin | Unlimited current
QUESTIONS,
    'ide-upload' => <<<'QUESTIONS'
What file extension is used for an Arduino sketch? | .ino | .jpg | .xlsx
Which function runs once after reset? | setup() | loop() | printForever()
Which function is repeatedly called by the Arduino framework? | loop() | setup() | upload()
What does Verify check? | Whether the sketch builds for the selected board | Whether every physical wire is correct | Whether a motor is safe for all loads
Why must the board and port selections match the connected device? | Uploading needs the correct target and connection | They choose the teacher's name | They replace the USB cable
Which order is appropriate for the first sketch? | Select board and port, verify, upload, observe | Observe, remove code, select a random board | Upload a picture as firmware | Short the board before selecting a port
What does a successful upload prove? | The program was transferred, not that all behavior is correct | Every circuit is safe | Every algorithm is correct | All sensors are calibrated
Which baud setting should the Serial Monitor use for Serial.begin(9600)? | 9600 | 115200 regardless of the sketch | 0 | The lesson number
What is a likely cause of an upload port not appearing? | A charge-only or disconnected USB cable | Too many spaces in a comment | A long sketch title | A missing LED resistor only
Which error should usually be investigated first? | The earliest relevant compiler diagnostic | The last warning without context | Only the window color | The leaderboard rank
What belongs inside the braces of setup()? | One-time initialization statements | A second unrelated sketch file | Household wiring instructions | The computer login password
Why save before editing an experiment? | To preserve a known working version | To power an LED automatically | To change the microcontroller type | To guarantee a perfect quiz
What is the purpose of Serial.println()? | Sending a value followed by a line ending | Reading the breadboard voltage automatically | Restarting the USB driver | Uploading a library
Which is good evidence for this lesson? | The sketch, selected board and an observation of its output | A screenshot of someone else's login | Only a project title | A copied claim that it worked
What should be done when the wrong board was selected? | Select Arduino Uno for this R3 lesson and rebuild | Increase the supply voltage | Delete every driver at random | Connect a motor directly to TX
QUESTIONS,
    'blink' => <<<'QUESTIONS'
What does pinMode(8, OUTPUT) configure? | Pin 8 as an output | Pin 8 as ground | Eight analog inputs
What does digitalWrite(8, HIGH) request? | A HIGH output level on pin 8 | Eight pulses every second automatically | Reading pin 8
How many milliseconds are in one second? | 1000 | 100 | 10
For an LED wired from pin through resistor to GND, what normally lights it? | HIGH | LOW | INPUT
What is the period of 500 ms on and 500 ms off? | 1000 ms | 500 ms | 250 ms
What is the approximate frequency of a 1-second on, 1-second off blink? | 0.5 Hz | 2 Hz | 1000 Hz | 60 Hz
Which statement pauses normal sketch execution for 250 milliseconds? | delay(250); | digitalRead(250); | pinMode(250); | Serial.begin(250) always
Why does a reversed LED often stay off? | Its polarity prevents normal forward current | The loop is necessarily absent | Its resistor stores the code | USB cannot power any LED
Which change creates a shorter visible on-time? | Reduce the HIGH-state delay | Increase the HIGH-state delay | Remove GND | Short the resistor
What does OUTPUT mean in this experiment? | The pin drives a signal to the circuit | The pin measures a sensor only | The pin becomes an unlimited supply | The sketch is downloaded as a photo
What should be recorded for a fair timing comparison? | Both on-time and off-time | Only the LED color | Only the file name | Only the USB port number
Why keep a series resistor when changing blink speed? | Timing changes do not remove the need for current limiting | Faster blinking requires a short circuit | The resistor controls the baud rate | The resistor stores delay values
Which loop gives a 25 percent on-time duty cycle using delays? | 250 ms HIGH, 750 ms LOW | 750 ms HIGH, 250 ms LOW | 500 ms HIGH, 500 ms LOW | 1000 ms HIGH, 0 ms LOW
What does the built-in LED allow you to test first? | Uploading and timing without an external LED circuit | Every sensor at once | A motor's stall current | Mains wiring
What limitation does a long delay introduce? | Other normal loop tasks wait during the delay | The sketch is permanently erased | The board loses all power | All variables become text
QUESTIONS,
    'sketch-logic' => <<<'QUESTIONS'
What does a named variable store? | A value the program can use | A physical wire | A USB driver
Why use const for an LED pin number? | To express that the pin assignment should not change | To make the pin analog | To power the board
What is the value after int count = 2; count = count + 3;? | 5 | 23 | 2
Which construct chooses between two actions? | if / else | A comment alone | A file extension
Why name a delay value blinkMs? | The name communicates its meaning and unit | It changes milliseconds into volts | It removes all errors
Which declaration is suitable for a fixed LED pin? | const byte ledPin = 8; | ledPin == 8; | const = ledPin; | byte 8 = ledPin;
What does count++ do? | Increases count by one | Doubles count | Converts count to text | Resets the board
Which comparison tests whether a value exceeds 500? | value > 500 | value = 500 | value + 500 | value << 500 always
What is a benefit of a reusable function? | A named operation can be called without duplicating its code | It eliminates all testing | It changes the wiring automatically | It powers a servo
Which symbol ends a simple assignment statement? | Semicolon | Question mark only | Colon only | A quotation mark
What is the result of 3 * 4 + 2? | 14 | 18 | 9 | 342
Why use braces for a multi-statement if body? | To group the statements controlled by the condition | To increase current | To select a board | To make every value constant
What should a loop counter do for a finite loop? | Move toward a condition that eventually stops the loop | Stay unchanged forever | Become the USB voltage | Store the project password
Which value is Boolean? | true | 5V | LED_BUILTIN as text only | The USB connector
Which test best checks a threshold decision? | Values just below, at and above the threshold | Only one random value | Only the largest possible number | No input at all
QUESTIONS,
    'buttons' => <<<'QUESTIONS'
With INPUT_PULLUP and a switch to GND, what is a released button normally read as? | HIGH | LOW | 1023
With the same wiring, what is a pressed button read as? | LOW | HIGH | 255
What does digitalRead() return for this button? | HIGH or LOW | A temperature in Celsius | A distance in centimeters
Why avoid an undefined floating input? | Its reading can fluctuate unpredictably | It saves too much memory | It forces the LED to be red
What is switch bounce? | Rapid contact transitions during one physical press | A software upload | An increase in USB voltage
Which configuration enables the internal pull-up resistor? | pinMode(2, INPUT_PULLUP); | digitalWrite(2, INPUT_ONLY); | analogRead(OUTPUT); | pinMode(2, MOTOR);
Which connection matches this lesson's pull-up design? | Button between D2 and GND | Button directly between 5V and GND | Button between two unrelated supplies | Button connected only to itself
Which condition detects a press in this circuit? | digitalRead(2) == LOW | digitalRead(2) == 1023 | analogWrite(2, 255) == HIGH | pinMode(2) == true
Why can one press increment a counter several times? | Bounce or repeatedly counting the held state | The button contains a computer virus | Every input is analog only | LOW always means two presses
What is edge detection? | Detecting a change from released to pressed | Reading only the side of the board | Increasing the pull-up voltage | Cutting the breadboard rail
What is a suitable debounce approach? | Accept a new state only after it remains stable briefly | Count every transition immediately | Remove ground | Increase the motor load
Which naming choice is clearest? | buttonPressed | xxxxxxxxx | usbPassword | maximumVoltageWhenUnknown
What should happen when a held button is meant to count once? | Count the transition, then wait for release before another count | Count every loop iteration | Reset the computer | Remove the pull-up
Which test set is most useful? | Quick press, long hold, release and repeated presses | Only a screenshot of code | One reading without wiring | Only the project title
Why must an output pin not be wired directly to a pressed-to-ground switch without a safe design? | A HIGH output could be shorted to GND | The switch needs a password | It changes C++ keywords | It makes loop run once
QUESTIONS,
    'traffic-lights' => <<<'QUESTIONS'
What is a state in a traffic-light model? | A defined phase with specified outputs | A country name in every sketch | An analog pin number only
Why use a separate series resistor for each LED? | Each LED needs its own current-limited path | All LEDs then become sensors | It prevents uploading
Which output condition should be avoided in a simple conflicting traffic model? | Both conflicting directions green | Red on one direction | All red during a planned transition
What should happen after a request is accepted? | Follow the planned safe sequence | Immediately ignore the current phase | Randomly change pins
Why test a model with a state table? | To check outputs and timing for every phase | To replace all circuit checks | To make the LEDs use mains power
Which information belongs in a state table? | Phase, outputs, duration and next phase | Only student nicknames | Only wire colors | Only the IDE version
What does a pedestrian request button represent? | An input event | A high-current supply | A program comment | A compiler diagnostic
Why might an all-red interval be used in a teaching model? | To separate conflicting movements in the planned sequence | To prove road certification | To power every LED from one pin | To remove testing
Which order is suitable for the basic three-LED demonstration? | Red, green, amber, then red | Every LED on forever | Random colors with no timing | Green and red alternating without a plan
If red lasts 3 s, green 3 s and amber 1 s, what is one cycle? | 7 s | 3 s | 9 s | 1 s
What can make a button unresponsive during a long delay? | The loop is blocked and not checking the button | The button is too colorful | The resistor changes baud rate | The board stores no code
Which statement gives the most responsible project claim? | This is an educational traffic model only | This is ready to control a real road | No testing is required | An LED proves all safety rules
Which debugging check should come before changing every delay? | Verify that each LED is mapped to the intended pin | Remove all resistors | Increase supply voltage | Hide the state table
What should a transition function do? | Set all outputs required for the new phase | Change only a random output | Forget the previous input | Disable every condition permanently
Which extension improves the model after the basic sequence works? | Add a debounced request and document safe transitions | Connect it to a real intersection | Remove the red phase | Ignore repeated requests without testing
QUESTIONS,
    'analog-input' => <<<'QUESTIONS'
What range does analogRead normally return on an Uno R3? | 0 to 1023 | 0 to 255 | 1 to 14
What is the wiper of a potentiometer? | Its adjustable middle contact | A USB reset button | A motor driver output
Which pin can read the potentiometer voltage in this lesson? | A0 | GND only | RESET only
Why connect the potentiometer end terminals to 5V and GND? | To create an adjustable voltage divider | To short the supply deliberately | To store more code
What should happen before moving the potentiometer wires? | Disconnect power | Turn the supply to mains voltage | Remove the ground label
What does an ADC do? | Converts an analog voltage into a digital number | Converts every number into motor power | Creates a Wi-Fi network | Replaces the USB port
With a 5V reference, an input near 2.5V is approximately which reading? | 512 | 5 | 2550 | 10230
Which expression estimates input volts for this lesson? | reading * 5.0 / 1023.0 | reading + 1023 | reading * 255 | reading / 5 without context
Why can a real reading vary slightly while the knob is still? | Noise and reference variation affect measurements | Every variation means broken code | The IDE is changing the resistor | The knob requires internet
What is a good way to display a changing reading? | Serial.println(analogRead(A0)); | digitalWrite(A0, 1023); | Serial.begin(A0) every line | pinMode(1023, OUTPUT);
What does turning the knob change? | The wiper voltage | The microcontroller model | The USB connector shape | The stored sketch file name
Which value is above a threshold of 600? | 750 | 599 | 600 when testing strictly greater | 0
Why should a sensor not feed a voltage beyond the board's permitted input range? | It can damage the input | The reading becomes a safe universal value | The pin automatically accepts any voltage | It only changes the font
Which record is useful for calibration? | Knob position, measured voltage and ADC reading | Only a photo of the box | Only the class code | Only the quiz title
Which term describes limited measurement steps? | Resolution | Indentation | Authentication | Concatenation
QUESTIONS,
    'pwm' => <<<'QUESTIONS'
What does PWM vary? | The fraction of each cycle spent HIGH | The Uno's logic HIGH voltage continuously | The USB cable length
What range is commonly passed to analogWrite on Uno R3 PWM pins? | 0 to 255 | 0 to 1023 | 1 to 10000
Which pin supports hardware PWM on Uno R3? | D5 | D4 | D8
For an active-HIGH LED, what does analogWrite(5, 0) request? | Off | Full brightness | An analog sensor reading
What does a duty cycle near 50 percent mean? | HIGH for about half of each PWM cycle | Exactly 50V on the pin | Fifty program uploads
What does analogWrite(5, 255) normally request? | Continuously HIGH output | Continuously LOW output | Read the sensor | A guaranteed 255V output
Which expression maps an ADC reading to approximately an 8-bit duty value? | reading / 4 | reading * 1023 | reading + 5000 | reading / 0
Why is PWM not a true variable-voltage DAC output on Uno R3? | The pin switches between logic levels | It has no output pins | It always outputs 2.5V | It measures current directly
What would an oscilloscope show for ordinary PWM? | Pulses with changing HIGH time | A password | Only a constant arbitrary voltage | No electrical signal
Which is required for an external LED fade circuit? | A resistor in series with the LED | A short between 5V and GND | A motor directly on the pin | Removal of all ground wires
What does increasing a PWM value generally do for this LED wiring? | Increases perceived brightness | Always changes its physical color | Increases flash memory | Selects a new USB port
Why should you clamp a calculated duty value? | To keep it within the intended 0 to 255 range | To prevent all input readings | To create a negative supply | To replace the resistor
Which pair are both Uno R3 PWM pins? | D3 and D6 | D4 and D8 | D2 and D7 | GND and RESET
Which test checks a full fade range? | Duty values 0, about 128 and 255 | Only one middle value | Only an unplugged sensor | Only the program title
When adding timer-based libraries, what should you check? | Whether their timers affect PWM pins in use | Only the class nickname | Whether code comments are uppercase | Whether the USB cable is green
QUESTIONS,
    'rgb' => <<<'QUESTIONS'
How many independently controlled color channels are in a basic RGB LED? | Three | One | Seven
For a common-cathode RGB LED, where does its common lead connect? | GND | RESET | A random digital output
Why use one series resistor per color channel? | Each channel needs current limiting | It makes the LED a motor | It increases memory
Which colors of light combine to produce yellow in the RGB model? | Red and green | Blue and green only | Blue alone
Why check whether the LED is common-anode or common-cathode? | The wiring and drive logic differ | They have identical connections in every circuit | It changes C++ punctuation
Which drive value gives full red for the stated common-cathode wiring? | Red 255, green 0, blue 0 | Red 0, green 255, blue 255 | All channels 0 | Only the common lead at 255V
What is additive color mixing? | Combining emitted light from color channels | Mixing paint in the breadboard | Increasing the supply above specifications | Combining three passwords
Which statement is correct about RGB package pin order? | Verify the particular part's pinout | Every package uses the same lead order | The longest lead is always red | Colors can replace a datasheet
What does RGB (255, 255, 255) approximate for common-cathode wiring? | White light | Off | Pure blue | Pure green
What does RGB (0, 0, 0) produce in that wiring? | Off | White | Yellow | Maximum current in every channel
Which set of pins can drive three PWM channels on Uno R3? | 3, 5 and 6 | 2, 4 and 7 | 0, 1 and RESET | GND, 5V and VIN
For a common-anode channel, why might a duty value be inverted? | LOW time turns the channel on | The LED becomes a sensor | HIGH always means brighter in every wiring | It converts PWM to Wi-Fi
Why might equal channel values not look perfectly balanced? | Channel efficiencies and human color perception differ | The sketch has no extension | The board cannot control more than one LED | All three channels are physically identical
Which project evidence demonstrates controlled mixing? | A table of RGB settings and observed colors | Only the USB cable photo | Only a saved nickname | A claim that every color is equally bright
Which is an appropriate change after checking wiring? | Adjust one channel at a time and record the result | Remove every channel resistor | Connect all leads to the same node | Apply household voltage
QUESTIONS,
    'light-sensor' => <<<'QUESTIONS'
What happens to a typical LDR's resistance as illumination increases? | It decreases | It always becomes exactly zero | It stores a new sketch
Why combine an LDR with a fixed resistor? | To form a measurable voltage divider | To create a motor | To remove the need for power
For 5V-LDR-A0-10k-GND, which condition generally gives a higher A0 reading? | Brighter light | Complete darkness | Disconnecting the sensor
Why calibrate a night-light threshold in the actual classroom? | Light levels and components vary | Every classroom has exactly the same reading | Calibration replaces wiring
What is hysteresis used for? | Reducing rapid switching near a threshold | Increasing USB voltage | Erasing analog readings
For the stated divider, which reading is more likely in darkness? | 120 | 900 | 1023 in every case | 5000
Which condition switches the model light on below a reading of 400? | lightValue < 400 | lightValue > 400 | lightValue = 400 | lightValue + 400
What should a calibration table include? | Lighting condition and measured ADC reading | Only the lesson title | Only a copied graph with no units | A password
Does an LDR divider reading directly represent lux? | No, a calibration model would be needed | Yes, every ADC count equals one lux | Yes, but only with red wires | It always represents Celsius
Which design uses separate on and off thresholds? | On below 350 and off above 450 | On and off for every single count change | On at all values | Off only after unplugging
Why can a lamp aimed at its own LDR create unwanted behavior? | The output changes the sensor's measured light | The LDR uploads code | The lamp becomes the USB port | The board loses its stored program
Which is a good test for a night-light? | Bright, dim and boundary lighting conditions | Only one reading outdoors | Only a screenshot of the source | A test without the sensor connected
What should you do if results are reversed from the lesson? | Check divider orientation and adjust the logic deliberately | Increase the supply beyond specifications | Remove the fixed resistor | Ignore the observed readings
Why average several readings? | To reduce some short-term measurement variation | To guarantee an exact lux measurement | To replace the sensor | To create motor torque
Which is an appropriate classroom application? | A prototype light reminder | A certified emergency lighting controller | Direct switching of building mains | An unsupervised high-voltage lamp circuit
QUESTIONS,
    'buzzer' => <<<'QUESTIONS'
Which function can generate a tone on a suitable passive piezo? | tone() | analogRead() | strlen()
What quantity does the tone frequency describe? | Cycles per second | LED resistance | Battery capacity
Which function stops a tone started with tone()? | noTone() | stopAllElectricity() | Serial.endTone()
Do active buzzers and passive piezos always use the same drive method? | No, identify the part before use | Yes, all buzzers are identical | Only if the USB cable is short
Why keep classroom tones brief and moderate? | To protect comfort and avoid disturbing others | To increase flash memory | To remove the need for a driver
What unit is used for the frequency argument of tone()? | Hertz | Centimeters | Volts | Degrees Celsius
Which call requests a 1000 Hz tone for 200 ms? | tone(8, 1000, 200); | tone(1000, 200, 8); | digitalRead(1000); | delay(8, 1000, 200);
What is the main difference between pitch and duration? | Pitch depends on frequency; duration is how long it lasts | They are always the same quantity | Pitch is measured in volts | Duration determines the USB port
Which part is suitable for the low-current example? | A teacher-checked passive piezo | An unknown high-current siren on GPIO | A mains speaker amplifier | A DC motor used as a resistor
Why inspect current requirements before connecting a sound module? | Some modules need a driver rather than GPIO power | All modules use zero current | Code length determines safe current | Color determines pin current
Which pattern communicates two distinct alerts? | Two documented sequences with different timing | Random noise without meaning | Continuous sound at every reading | An unpowered buzzer
What could a missing common ground cause in a driven module? | An undefined signal reference | Automatic sound calibration | Extra program memory | Guaranteed silence for all circuits
Which response is best if the buzzer is much louder than expected? | Stop the test and review the part and drive circuit | Place it next to someone's ear | Increase volume immediately | Ignore the class
Which statement about tone and Uno timers is sensible? | Check timer conflicts when combining tone with PWM activities | tone never interacts with hardware timers | Every PWM pin remains unaffected by all libraries | Changing a comment fixes timer conflicts
Which test demonstrates the alarm rule? | Trigger and clear the condition while recording the sound state | Only upload the code | Only rename the file | Hold the sensor at one value forever
QUESTIONS,
    'servo' => <<<'QUESTIONS'
What does a hobby positional servo control? | A target angular position within its supported range | Unlimited forward speed only | The USB baud rate
Which library is used for the positional servo example? | Servo | EEPROM only | Wire only
Why use a suitable external regulated supply for a servo? | Servo current can exceed what the board should supply | It changes degrees into seconds | Servos store the sketch
What must the servo signal and Uno share? | A common ground reference | A direct short between signal and 5V | The same password
Why start testing with the horn free of obstacles? | To avoid mechanical strain and unexpected movement | To increase GPIO current | To remove the need for code
What does attach(9) specify for a Servo object? | The control signal pin | The external supply voltage | The number of servos in the kit | The baud rate
What does write(90) request for a typical positional servo? | A target around the middle of its angle range | A guaranteed exact calibrated 90 degrees | Ninety volts | Ninety rotations each second
Does every hobby servo support the same angle range? | No, check its specification and mechanical limits | Yes, all rotate exactly 360 degrees | Yes, all stop at exactly 180 degrees | No servo has limits
What distinguishes a continuous-rotation servo? | Commands generally control speed and direction rather than a target angle | It always measures temperature | It needs no supply | It is the same as every positional servo
Why can a servo reset the Uno when powered poorly? | Current demand can cause supply voltage to drop | It changes setup into loop | Its horn erases source files | Every servo uploads a new sketch
Which is a safe first sequence after checking limits? | Test modest positions such as 20, 90 and 160 degrees | Force the horn against a hard stop | Twist the powered horn by hand | Drive it from an I/O pin as its power supply
What should you prevent when USB and an external supply are used? | Unintended backfeeding between positive supply rails | A common signal ground | A labeled wiring diagram | A saved sketch
Which evidence supports a servo assignment? | Requested positions, observed motion and a wiring diagram | Only a library name | Only an LED color | A copied claim of accuracy
What can happen when a mechanism is stalled? | Current and heating can rise | It needs no power | The board gains RAM | Angle commands become text
Which design change can improve a servo model? | Move through a planned sequence while respecting limits | Remove ground | Apply any available voltage | Tie the signal to an unknown battery
QUESTIONS,
    'ultrasonic' => <<<'QUESTIONS'
Which HC-SR04 signal starts a measurement? | Trigger | GND | VCC alone
Which signal pulse is measured for the return? | Echo | RESET | The USB LED
Why divide the sound travel distance by two? | The sound travels to the object and back | The sensor has two power pins | The board has two LEDs
What does a zero pulseIn result after a timeout mean here? | No valid echo arrived in time | An object is exactly at zero centimeters | The path is definitely clear
Why include an explicit timeout? | To bound waiting when an echo is missing | To increase GPIO voltage | To remove all measurement error
Using 0.0343 cm per microsecond, what distance corresponds approximately to a 1000 us round trip? | 17.15 cm | 34.3 cm | 1000 cm | 0.0343 cm
Which pair is appropriate for the example's signal pins? | D7 trigger and D8 echo | GND trigger and 5V echo directly shorted | RESET trigger and VIN echo | Two unrelated supplies
Why leave time between measurements? | To reduce interference from previous acoustic pulses | To erase old source code | To increase motor torque | To make every surface reflective
Which surface may give an unreliable echo? | A small, soft or strongly angled target | Every flat hard target always | The USB connector only | A code comment
What does pulseIn measure? | The duration of a specified signal level | The sensor's battery capacity | A resistance directly | The program file size
What is a suitable handling rule for an invalid reading? | Show invalid and use a planned safe output state | Treat it as an exact clear-path reading | Replace it with a fabricated distance | Ignore the timeout forever
Which physical reference should be used when comparing readings? | A ruler with a consistently defined sensor reference point | The code's indentation | The LED color | The lesson title
Which factor can affect the speed-of-sound approximation? | Air temperature | The source file extension | The class nickname | The USB port number
Why must the Echo voltage match the controller's input tolerance? | Excess voltage may damage a different board | All controllers accept every voltage | Echo is always a harmless software value | Only variable names matter
Which claim is appropriate for a kit distance alarm? | A supervised learning prototype with known limitations | A certified collision-prevention system | A replacement for human supervision | A guarantee that every object is detected
QUESTIONS,
    'temperature' => <<<'QUESTIONS'
Which sensor is specified in this lesson's worked conversion? | TMP36 | Every temperature sensor interchangeably | An LDR
What is the TMP36 approximate output at 0 degrees Celsius? | 0.5 V | 5 V | 50 V
For this TMP36 formula, what does 0.75 V represent approximately? | 25 degrees Celsius | 75 degrees Celsius | 0.75 degrees Celsius
Why must the sensor model be identified first? | Different sensors can need different conversions and pinouts | Every sensor uses the same formula | It changes the USB connector
What should a beginner avoid when testing a temperature sensor? | Flames, hot liquids and unsafe heat sources | Comparing readings at room temperature | Recording units
Which conversion fits TMP36 output voltage v in this lesson? | (v - 0.5) * 100 | v * 100 for every sensor | v + 1023 | 5 / v always
What result does 0.80 V give with that conversion? | 30 degrees Celsius | 80 degrees Celsius | 8 degrees Celsius | 130 degrees Celsius
Why is multiplying ADC counts by 5.0 / 1023.0 only an estimate here? | The assumed reference voltage and sensor have tolerances | All boards use exactly 5.000000 V | ADC counts are already Celsius | The formula contains no assumptions
What should accompany a recorded temperature? | Its unit and measurement conditions | Only the USB port | A class password | The code font
Which sensor cannot simply replace TMP36 without changing the model? | A thermistor divider with different characteristics | Another verified TMP36 with the same wiring | A checked identical sensor | A calibrated identical replacement
Why should you check the particular package's pinout? | Reversed supply connections can damage the sensor | The shortest lead is always ground on every sensor | Every sensor has the same package | Software prevents all reversed wiring damage
Which response helps investigate an implausible temperature? | Disconnect power, verify wiring and conversion, then retest | Declare the room is at the calculated value | Increase supply voltage | Hide the reading
What is calibration intended to improve? | Agreement with a suitable reference under stated conditions | The number of functions | The shape of the USB cable | Program memory capacity
Which threshold condition identifies a value above 30 degrees Celsius? | temperatureC > 30.0 | temperatureC = 30.0 | temperatureC + 30.0 | temperatureC < 0.0
Which use is appropriate for this lesson? | A classroom temperature-display prototype | A medical diagnosis instrument | A certified fire detector | An unsupervised heater controller
QUESTIONS,
    'lcd' => <<<'QUESTIONS'
What does 16x2 describe on this character LCD? | Sixteen columns and two rows | Sixteen volts and two amps | Two hundred sixteen pixels
Which library supports the lesson's parallel LCD example? | LiquidCrystal | Servo only | EEPROM only
What does lcd.setCursor(0, 1) select? | First column of the second row | Second column of the first row | The USB port
What is the contrast potentiometer used for? | Adjusting character visibility | Increasing flash memory | Choosing a baud rate
Why check whether a display uses a parallel interface or an I2C backpack? | Their wiring and initialization differ | They always have identical signal pins | They require no libraries
Which call initializes a 16-column, 2-row display in this example? | lcd.begin(16, 2); | lcd.begin(2, 1600); | analogRead(16, 2); | Servo.attach(16);
What does lcd.print("Ready") do? | Writes text at the current cursor position | Reads the contrast voltage | Uploads a library | Powers a motor
For a write-only parallel example, where is RW normally connected? | GND | 5V permanently for writing | RESET | An unrelated motor supply
Why should the backlight connection be checked against the module's specification? | Some modules need external current limiting | Every backlight accepts unlimited current | Its pins are software only | Backlights always use mains voltage
Why can characters remain from a previously longer value? | New text may not overwrite every old character position | The LCD copies the teacher's login | Every number has the same width | lcd.print always clears the screen
Which update method avoids unnecessary flicker? | Overwrite changed values and pad old characters when needed | Clear the screen on every fast loop pass | Disconnect contrast while running | Randomly change LCD pin assignments
Which constructor pins must match the physical circuit? | RS, enable and the four data pins used | Only VCC | Only the first text character | Only the lesson number
Which output is appropriate for row 2 of a sensor display? | A labeled value with units | A password | An unexplained random number | The contents of private student records
Why check power and contrast before rewriting the sketch for a blank LCD? | The display may be powered but unreadable | Software is always the only possible fault | LCDs never need ground | Every blank screen proves a broken processor
Which is a useful display test? | Write a short fixed message on both rows first | Start with every sensor and motor simultaneously | Remove the contrast control | Power it from an unknown source
QUESTIONS,
    'keypad' => <<<'QUESTIONS'
How is a basic 4x4 matrix keypad organized? | Four rows crossing four columns | Sixteen independent USB ports | One analog resistor per key in every keypad
Why scan one row at a time? | To identify a pressed row-column intersection | To increase the supply voltage | To erase previous keys
With a selected row LOW and pulled-up columns, what indicates a pressed key? | A LOW column reading | 1023 on every digital pin | A HIGH motor voltage
Why leave unselected rows high-impedance in this lesson? | To avoid driving conflicting output levels through pressed keys | To make every row a power supply | To increase flash memory
Why does this beginner activity ask for one key at a time? | Simple scanning does not resolve every multi-key ambiguity | The keypad has only one physical key | The code needs a password
How many positions are available in a 4x4 matrix? | 16 | 4 | 8 | 32
What maps a row and column index to a printed character? | A two-dimensional key map | A motor driver | A voltage regulator | The USB cable
What is keypad ghosting? | An ambiguous or phantom key result with certain multiple-key combinations | A compiler reinstall | A change in LED color | Loss of the classroom roster
Which is a safe row-scanning sequence here? | Make one row LOW output, read columns, restore that row to INPUT | Drive every row against another output | Short 5V directly to GND | Apply mains voltage to each row
Why debounce keypad input? | One physical press can produce repeated transitions | It changes a character into a motor | It guarantees secrecy | It powers the LCD
Which key map entry corresponds to row 0, column 0 in the example? | '1' | 'D' | '0' | '#'
What must be checked before assigning row and column pins? | The keypad's actual connector mapping | Only its sticker color | The number of comments | The USB speed
What is a sensible test sequence? | Press each key once and compare the displayed character with the key map | Press all keys at once only | Test without a ground reference | Assume a matching connector means matching wiring
What should a key-triggered counter do to avoid repeated counts during a hold? | Detect a new accepted press rather than every scan | Increment on every loop pass | Disable all input checks | Increase PWM frequency
Which use fits this introductory lesson? | A menu selector with fictional entries | A secure real-world access-control lock | A payment PIN collector | A replacement for certified security hardware
QUESTIONS,
    'joystick' => <<<'QUESTIONS'
What do the two analog joystick axes commonly contain? | Potentiometers | Two ultrasonic speakers | Two USB processors
What does the center button commonly provide? | A digital switch input | Unlimited motor current | A temperature reading
Why use a dead zone near the resting position? | To avoid reacting to small center variations | To erase all analog values | To increase the supply voltage
Are X and Y direction signs identical for every mounting orientation? | No, verify the orientation and calibrate | Yes, always | Only when the code has no variables
Which input setting fits a button wired to GND? | INPUT_PULLUP | OUTPUT HIGH | MOTOR_POWER
Which pins can read the two analog axes in this example? | A0 and A1 | 5V and GND as analog inputs | RESET and VIN | USB RX and the power jack only
What are plausible approximate centered ADC values? | Near the middle of the 0 to 1023 range | Always exactly zero | Always exactly 5000 | Always negative
Which rule gives a simple center dead zone? | Treat values from 450 to 570 as centered after calibration | Require exactly 512 forever | Ignore every input | Multiply voltage beyond the supply
How can a reversed axis be handled deliberately? | Reverse the mapped direction after checking the circuit | Remove the common ground | Increase the supply voltage | Rename setup to main
What does mapping the joystick reading to a servo command require? | A limited output range matching safe mechanical travel | A direct motor-to-input connection | A guaranteed exact center of 512 | A mains power supply
Which reading should be tested for each axis? | Both extremes and the released center | Only a random position | Only the source file size | Only the button color
What does a LOW center-button reading mean for the pull-up circuit? | The button is pressed | The supply is necessarily failing | The axis is at maximum | No joystick is connected in every case
Why should output commands be constrained? | Unexpected readings should not request unsafe positions or duty values | The joystick stores too many passwords | Every servo accepts any command | Constraining removes the need for power
Which observation belongs in a calibration record? | Resting and extreme readings for X and Y | Only the kit price | Only the classroom password | Only the IDE theme
Which use is suitable for the first experiment? | Displaying direction words before connecting a mechanism | Driving an unsupervised vehicle immediately | Connecting motors directly to the joystick outputs | Using it as a certified emergency control
QUESTIONS,
    'pir' => <<<'QUESTIONS'
What does a PIR sensor respond to? | Changes in infrared radiation across its sensing zones | Every object regardless of conditions | The source code text
Why allow a PIR module to settle after power-on? | Its output may be unstable during warm-up | It needs to download a password | It is charging the Uno's memory
Does a basic PIR module identify a person? | No, it does not establish identity | Yes, it recognizes every face | Yes, it knows school IDs
Why does a stationary person sometimes stop triggering PIR output? | The sensor mainly detects changes in its field | All motion sensors are broken | HIGH means no supply
Which statement is an appropriate privacy claim? | This model detects motion-like events, not a person's identity | It proves who entered a room | It records every person's intentions
Which output is typical for many kit PIR modules? | A digital event signal | A photograph of each person | A precise distance for every target | A universally accurate head count
What should you check before connecting the module output? | Voltage compatibility and the module's pinout | Only the module's color | Whether its name is short | The number of comments
What can cause a PIR event other than a learner entering? | Changing thermal conditions within its field | A code comment by itself | The worksheet title | The microcontroller's nickname
Why test across and toward the sensing field? | Detection depends on movement through the sensing zones | Every direction is always equally detectable | It changes the sensor's identity | It removes the need for calibration
What can a module's adjustable delay affect? | How long its output remains active after detection | The Uno's flash memory size | The USB connector shape | The text encoding
Which is a responsible output action in a classroom prototype? | Light an indicator and log an event | Claim that attendance identity is confirmed | Trigger unsafe machinery | Publish a student's personal record
What should the display show during warm-up? | A clear warming-up state | A fabricated accurate event count | Every learner's school ID | A claim of certified security
Which test distinguishes the signal from the alert behavior? | Observe the raw input and output indicator separately | Change every wire and threshold at once | Remove power while reading | Ignore module settings
What does retrigger behavior depend on? | The module's mode and timing settings | The filename alone | The color of the USB plug | The classroom code
Which limitation should students report? | PIR events are not reliable proof of identity or exact occupancy | Every motion event identifies one specific student | A stationary person is always detected | No environment affects PIR readings
QUESTIONS,
    'motor-driver' => <<<'QUESTIONS'
Why use a motor driver? | A motor's current and switching needs exceed a GPIO's role | It makes every motor wireless | It removes the need for power
What is an H-bridge used for? | Reversing voltage direction across a DC motor | Measuring room temperature only | Increasing program memory
What must the motor supply and control circuit share in the stated non-isolated design? | A common ground reference | A direct short across their positive and negative rails | A password
Why plan for motor stall current? | It can be much greater than no-load current | It is always zero | It determines the sketch extension
What should be checked before reversing a classroom motor? | Stop first and follow the driver and mechanical limits | Remove the driver | Apply more voltage without checking
In a suitable L293D-style driver, what can PWM on the enable input control? | Average drive level and approximate speed under load | Exact position without feedback | The file name | Sensor calibration automatically
What do the two direction inputs determine in a basic H-bridge setup? | The commanded direction or stop mode | The student's nickname | The USB baud rate | The lesson's grade level
Why should wheels or shafts be clear during initial testing? | Unexpected rotation can catch objects or fingers | It increases flash memory | It replaces current limits | It is required by C++ syntax
What protects against inductive switching voltage in a properly designed driver? | Suitable flyback protection | A code comment alone | Removing ground | A larger filename
Which supply choice is appropriate? | A teacher-approved supply within motor and driver ratings | Any source that fits the connector | Household mains on the breadboard | GPIO power for every motor
What does a motor speed command prove about real speed? | Actual speed depends on load, supply and the motor | The requested duty always equals revolutions per minute | Every motor runs at the same speed | No measurement is needed
What is a safe first stop command for the demonstrated driver logic? | Set enable to zero before changing direction | Drive the motor directly from RESET | Short the supply | Reverse every wire while powered
Why inspect a driver's supply jumpers? | Incorrect settings can backfeed or connect supplies unintentionally | They select the course grade | All drivers have identical jumpers | Jumpers change source indentation
Which data supports an engineering conclusion? | Duty setting, supply, load and observed behavior | Only the kit color | Only one unexplained screenshot | Only the project nickname
Which rule remains true after adding a driver? | Stay within all electrical and mechanical limits | Any motor is now safe | Mains may be connected to GPIO | Ground is never needed
QUESTIONS,
    'millis' => <<<'QUESTIONS'
What does millis() report? | Elapsed milliseconds since the sketch started | An analog voltage | The number of uploaded files
Why can millis-based scheduling improve responsiveness? | The loop can check inputs between scheduled actions | It stops all code from running | It removes every timing decision
Which type is used for millis timestamps in the Uno example? | unsigned long | char only | bool only
Which condition is appropriate for an elapsed-time check? | now - previous >= interval | now == interval only | previous = now always
Why avoid requiring an exact timestamp equality? | The loop might not execute at that precise millisecond | Equality increases voltage | millis returns only text
Which comparison is designed to work across unsigned millis rollover for normal short intervals? | (unsigned long)(now - previous) >= interval | now >= previous + interval in all cases | now == previous forever | interval / 0
What should be updated when a scheduled LED action occurs? | The stored previous timestamp according to the chosen timing policy | The USB connector | The microcontroller model | The sensor's physical label
What makes a loop nonblocking in this lesson? | It repeatedly checks whether work is due without a long delay | It contains no variables | It never reads inputs | It permanently disables interrupts
Why use separate timestamps for two independent tasks? | Each task needs its own schedule | It doubles the supply voltage | It erases old readings | It guarantees identical periods
Can a long pulseIn or other blocking call still reduce responsiveness? | Yes, nonblocking LED timing does not make every other operation nonblocking | No, millis makes all functions instantaneous | No, all sensors return immediately | Only if the filename is long
If a task interval is 500 ms, approximately how often is it due? | Twice per second | Five hundred times per second | Once per minute | Once per hour
Which policy is simpler but can drift when work takes time? | Set previous to the current time after the action | Add exactly one interval to previous always | Use a hardware timer only | Never update previous
What should an emergency input test include in this model? | Trigger it during every phase of scheduled activity | Test only while nothing is running | Test only after unplugging | Assume a fast loop proves every response
Which quantity should be measured for responsiveness? | Time from an input event to the required output change | The comment count | The source filename length | The LED's package size
Why document timing assumptions? | Sensor calls, serial output and libraries can affect real timing | All code executes instantly | millis prevents all missed events | Timing never depends on workload
QUESTIONS,
    'robot-functions' => <<<'QUESTIONS'
Why separate sensing, decision and output code into functions? | Each part can be understood and tested more clearly | Functions remove the need for wiring | Every function creates a new processor
What should a distance-reading function report when its measurement times out? | A clear invalid result the caller handles | A fabricated clear-path distance | The classroom password
What is a boundary test? | Testing values near a decision limit | Testing only the project's title | Removing every boundary
What is a useful debugging log? | Labeled inputs, decisions and outputs | Only repeated words saying it works | Private account passwords
Why change one suspected cause at a time? | To connect the change with its observed effect | To guarantee all failures vanish | To avoid saving code
Which function name best communicates intent? | readDistanceCm | xxxxx | password2 | thing
What is a function parameter? | A value supplied to a function when it is called | A physical motor terminal | A USB connector type | A copy of the teacher's account
What is a return value? | Information a function sends back to its caller | Electricity returned to the supply | A deleted source file | A compulsory delay
Which result should a decision function produce? | A defined state or action based on validated inputs | A random motor command | An unrelated password | A new processor type
Why keep sensor readings with units in variable names or documentation? | To avoid mixing centimeters, milliseconds and other quantities | To make every value larger | To remove conversion errors automatically | To select the USB port
What does a failing test tell a developer? | Some behavior differs from the stated expectation and needs investigation | The student can never learn robotics | All components must be discarded | The computer is always broken
Which is an appropriate test double for a decision function? | Deliberately supplied normal, boundary and invalid readings | An unknown high-voltage supply | A copied password | A random permanent output
What is regression testing? | Rechecking earlier working behavior after changes | Removing earlier evidence | Reinstalling every tool | Testing only the newest filename
Why document the known limitations of a robot model? | Users and reviewers need to understand what it can and cannot demonstrate | It lowers the physical voltage | It replaces every test | It proves the prototype is certified
Which evidence best supports an improved version? | A saved revision and before-and-after test results | A renamed file with no tests | Only a prettier enclosure | A claim that errors cannot happen
QUESTIONS,
    'capstone' => <<<'QUESTIONS'
What should be agreed before building a capstone? | A measurable problem statement and success criteria | Only the enclosure color | A claim of perfect reliability
Why plan a safe state for missing sensor data? | A failed measurement must not trigger unsafe movement | It increases the quiz score automatically | It replaces the power supply
Which evidence demonstrates independent learning? | A student's explanation and recorded tests of their own revision | Only a copied finished sketch | A photo with no labels
Why separate prototype claims from real-world safety claims? | Classroom tests do not certify a device for safety-critical use | Every prototype is automatically certified | An Uno guarantees safe decisions
What should happen after a failed capstone test? | Investigate, revise and retest while keeping the evidence | Delete the failed result | Declare success anyway
Which is a measurable success criterion for the model? | Warn for valid readings below 15 cm and enter a safe state on timeout | Be impressive | Have many wires | Never record a failure
What belongs in a capstone wiring document? | Pin names, supply information, component values and common grounds | Only nicknames | Only the school logo | A password list
Which test set is most complete? | Normal, boundary, invalid input and repeated-operation tests | One successful demonstration | Only a code screenshot | Only an unplugged circuit
Why assign rotating roles during group prototyping? | More learners practise wiring, coding, testing and explaining | Only one learner should understand the project | It removes individual accountability | It increases GPIO current
What should the final demonstration explain? | How inputs lead to decisions and outputs, with limitations | Only the project title | The teacher's login details | Why tests are unnecessary
Which action protects the assessment process? | Review the saved sketch and each learner's own explanation | Award mastery from XP alone | Assume a copied video proves understanding | Skip the rubric
Which response is correct if the servo moves unexpectedly? | Stop and inspect the system before continuing | Hold the mechanism against a hard stop | Increase voltage immediately | Ignore the motion
What makes a useful reflection? | Explaining a changed design choice using test evidence | Repeating that everything is perfect | Listing only colors | Copying a password
Which improvement is driven by evidence? | Add hysteresis after observing chatter near a threshold | Add random wires because the board looks empty | Remove safety checks to shorten the file | Raise supply voltage without checking ratings
What is an appropriate final artifact? | A documented sketch, circuit plan, test log and individual reflection | Only a leaderboard rank | Only a kit purchase receipt | A claim that no teacher review is needed
QUESTIONS,
];

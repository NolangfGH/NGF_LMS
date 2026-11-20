<?php

// Original application questions supplement the vocabulary and concept items.
return [
    'math7' => <<<'BANK'
polygons|What is the interior angle sum of a hexagon?|720°|540°|900°|108°|(6−2)×180°=720°.
percent|A ₱800 item is discounted by 15%. What is the sale price?|₱680|₱120|₱785|₱920|Subtract 0.15×800=120 from 800.
rational|What is −5/6 + 1/3?|−1/2|−2/3|1/2|−4/9|Rewrite 1/3 as 2/6, then −3/6=−1/2.
roots|A cube has volume 125 cm³. What is its edge length?|5 cm|25 cm|15 cm|625 cm|5³=125.
sets|20 students chose art, 16 chose music and 8 chose both. How many chose at least one?|28|36|12|44|20+16−8=28.
volume|What is the volume of a cylinder with radius 2 cm and height 7 cm?|28π cm³|14π cm³|49π cm³|4π cm³|πr²h=π×4×7.
algebra|Solve 4x−3=17.|5|3.5|−5|20|Add 3, then divide by 4.
statistics|What is the median of 3, 8, 5, 10, 4?|5|6|8|4|Order 3,4,5,8,10; the third number is 5.
probability|A bag has 3 green and 5 yellow equal-size tokens. What is P(green) for one random draw?|3/8|5/8|3/5|1/3|3 favorable out of 8 total.
BANK,
    'math8' => <<<'BANK'
polynomial|Expand (x+5)².|x²+10x+25|x²+25|x²+5x+25|x²−25|The two cross products each equal 5x.
factoring|Simplify (x²−9)/(x−3), given x≠3.|x+3|x−3|x²+3|1|Factor (x−3)(x+3) and cancel the common factor.
coordinates|What is the slope through (1,3) and (4,9)?|2|1/2|3|6|Slope=(9−3)/(4−1)=2.
systems|Solve x+y=10 and x−y=4.|x=7, y=3|x=3, y=7|x=5, y=5|x=6, y=4|Add equations to get 2x=14.
inequalities|Solve −3x≤12.|x≥−4|x≤−4|x≥4|x≤4|Dividing by a negative reverses the inequality.
geometry|Two triangles have matching sides 4, 6 and 7 cm. Which congruence criterion proves equality of shape and size?|SSS|AAA|SA|SSA for every triangle|Three matching side lengths establish SSS congruence.
parallel|Can sides of 4 cm, 5 cm and 10 cm form a triangle?|No, 4+5<10|Yes, all lengths are positive|Yes, 4+10>5 is enough|Only if it is right-angled|Any two side lengths must sum to more than the third.
data|For ordered values 1,3,5,7,9,11, what is the median?|6|5|7|11|Average the two middle values, 5 and 7.
chance|Two fair coins are tossed. What is P(exactly one head)?|1/2|1/4|3/4|1|HT and TH are two of four equally likely outcomes.
BANK,
    'math9' => <<<'BANK'
quadratics|Find the roots of x²−7x+12=0.|3 and 4|−3 and −4|2 and 6|1 and 12|Factor as (x−3)(x−4).
functions|What is the vertex of y=2(x+1)²−3?|(−1,−3)|(1,−3)|(−1,3)|(2,−3)|Vertex form uses (h,k) where the factor is x−h.
variation|If y varies inversely with x and y=6 when x=4, find y when x=8.|3|12|2|24|xy=24; 24/8=3.
radicals|Simplify √98.|7√2|14√7|49√2|2√7|√98=√(49×2)=7√2.
quadrilaterals|A parallelogram has all sides equal and one right angle. What is its most specific classification?|Square|Trapezoid only|Nonrectangular rhombus|Kite only|Equal sides and four right angles characterize a square.
similarity|Similar triangles have corresponding sides 4 and 10. What corresponds to a side of 6 in the smaller triangle?|15|12|2.4|16|Scale factor=10/4=2.5; 6×2.5=15.
trigonometry|A right triangle has opposite leg 8 and adjacent leg 15 for angle θ. What is tan θ?|8/15|15/8|8/17|15/17|Tangent is opposite over adjacent.
statistics|A scatterplot trends downward as x increases. What association does it suggest?|Negative association|Positive association|Every value is equal|Certain causal dependence|Downward trend indicates negative association; causation is not established.
models|A rectangular garden has perimeter 24 m. What dimensions give its greatest area?|6 m × 6 m|2 m × 10 m|4 m × 8 m|12 m × 12 m|For fixed perimeter, a square maximizes rectangular area.
BANK,
    'math10' => <<<'BANK'
sequences|Find the 8th term of 4,7,10,13,…|25|28|21|24|4+(8−1)×3=25.
polynomials|Find the remainder of x³+2x−5 divided by x−2.|7|−1|3|0|Evaluate f(2)=8+4−5=7.
rational|What value is excluded from f(x)=(x+2)/(x−5)?|5|−2|0|2|The denominator is zero at x=5.
circles|An inscribed angle intercepts an arc of 124°. What is the angle measure?|62°|124°|248°|56°|An inscribed angle is half its intercepted arc.
coordinate|What is the distance between (−1,2) and (2,6)?|5|7|4|25|√(3²+4²)=5.
permutations|How many unordered pairs can be chosen from 6 learners?|15|30|12|36|6×5/2=15.
probability|A bag has 2 red and 3 blue tokens. What is P(two blue without replacement)?|3/10|9/25|2/5|3/5|3/5×2/4=3/10.
position|In an ordered distribution, about what fraction of values is at or below the first quartile?|One fourth|One half|Three fourths|All|The first quartile is the 25th percentile.
inquiry|A researcher wants to generalize to all four grades but surveys only Grade 10. What is the main concern?|Unrepresentative sampling|Too many grades sampled|No variability is possible|The sample is automatically random|The sample excludes three target grade levels.
BANK,
    'advmath7' => <<<'BANK'
logic|Which example disproves the statement “All rectangles are squares”?|A rectangle with sides 3 and 5|A square with side 3|A regular pentagon|A circle|A 3×5 rectangle is not a square.
number|Find the GCF of 48 and 72.|24|12|36|144|48=2⁴×3 and 72=2³×3²; common factor 2³×3.
modular|What is 29 modulo 7?|1|4|7|0|29=4×7+1.
ratios|A scale model is 1:20. A model measurement of 6 cm represents what actual length?|120 cm|30 cm|20 cm|0.3 cm|6×20=120 cm.
patterns|Find the next term of 1,4,9,16,25,…|36|30|35|49|These are squares 1² through 6².
geometry|How many regular hexagon interior angles meet around a point in a regular tiling?|3|4|5|6|Each angle is 120°, and 360/120=3.
counting|Four colors and three shapes create how many distinct color-shape pairs?|12|7|4|3|Multiply 4×3.
data|A graph starts its vertical axis at 49 rather than zero when comparing 50 and 52. What could happen?|Differences may appear exaggerated|The values automatically become equal|The sample size doubles|The median changes|Scale can change visual impression without changing data.
proof|Which expression proves that the sum of two odd integers is even?|2(a+b+1)|2(a+b)+1|a+b+1|2ab+1|(2a+1)+(2b+1)=2(a+b+1).
BANK,
    'advmath8' => <<<'BANK'
algebra|Calculate 49×51 using a special product.|2499|2500|2401|2601|(50−1)(50+1)=2500−1.
diophantine|Which nonnegative integer pair satisfies 3x+2y=14?|(4,1)|(3,3)|(2,5)|(0,6)|3×4+2×1=14.
functions|If f(x)=3x+1 and g(x)=x−2, find f(g(5)).|10|14|16|8|g(5)=3, so f(3)=10.
linear|For x,y≥0 and x+y≤8, which point maximizes 4x+y?|(8,0)|(0,8)|(4,4)|(2,6)|The objective is greatest at (8,0), value 32.
geometry|A triangle has remote interior angles 45° and 65°. What is the related exterior angle?|110°|70°|20°|90°|Sum the two remote interior angles.
transform|Reflect (−4,3) in the x-axis.|(−4,−3)|(4,3)|(4,−3)|(3,−4)|Reflection in the x-axis negates y.
combinatorics|How many ways can three items be chosen from five distinct items, without order?|10|60|15|5|5×4×3/(3×2×1)=10.
probability|A fair coin pays +₱2 on heads and −₱2 on tails. What is its expected net payoff?|₱0|₱2|−₱2|₱4|0.5×2+0.5×(−2)=0.
proof|Which is the contrapositive of “If n is divisible by 4, then n is even”?|If n is not even, then n is not divisible by 4.|If n is even, then n is divisible by 4.|If n is not divisible by 4, then n is not even.|If n is odd, then n is divisible by 4.|Negate and reverse the hypothesis and conclusion.
BANK,
    'advmath9' => <<<'BANK'
quadratic|What is the minimum of x²−8x+20?|4|20|−4|0|Complete square: (x−4)²+4.
inequalities|When does the arithmetic mean of two positive numbers equal their geometric mean?|When the two numbers are equal|When one is twice the other|When both are different|Never|Equality holds when their squared difference is zero.
functions|How does y=f(x+4) shift the graph of f?|4 units left|4 units right|4 units up|4 units down|The same output is reached 4 units earlier in x.
geometry|If a tangent from point P has length 6 and a secant from P has full length 9, what is its external segment length?|4|3|6|15|Power of a point: 6²=9×external length.
trig|For acute θ with sin θ=3/5, what is cos θ?|4/5|3/5|5/4|2/5|cos²θ=1−9/25=16/25.
vectors|Find the dot product of (2,−1) and (3,4).|2|10|−2|5|2×3+(−1)×4=2.
counting|If 11 students each choose one of 10 colors, what must happen?|At least two choose the same color|Everyone chooses a different color|Exactly four choose red|One color cannot be chosen|By the pigeonhole principle, 11 choices in 10 categories force a repeat.
statistics|A model predicts 24 but the observed value is 20. What is the residual?|−4|4|44|20|Observed minus predicted=20−24.
investigation|A model assumes a constant rate but new data show a changing rate. What is the best next step?|Revise the assumption and validate with evidence|Ignore the new data|Declare the model exactly correct|Delete the observations|Model validation requires adapting when evidence contradicts assumptions.
BANK,
    'advmath10' => <<<'BANK'
proof|If an induction proof establishes the base case but never proves the next case, what is missing?|Inductive step|Definition of the first term|An example of a negative number|The base case|Induction needs the implication from k to k+1.
polynomial|For x²−9x+20=0, what is the product of its roots?|20|9|−9|−20|Vieta product=c/a=20.
functions|What is log₃81?|4|3|27|78|3⁴=81.
analytic|What are the semi-axis lengths of x²/16+y²/9=1?|4 and 3|16 and 9|8 and 6|7 and 3|The denominators are squares of semi-axis lengths.
trig|What is the amplitude of y=3 sin x−2?|3|−2|1|5|Amplitude is the absolute vertical multiplier.
matrix|Find the determinant of the 2×2 matrix with rows (2,1) and (3,4).|5|11|8|−5|ad−bc=2×4−1×3=5.
probability|A random variable pays 0 with probability 1/4 and 8 with probability 3/4. What is E(X)?|6|2|4|8|0×1/4+8×3/4=6.
inference|When comparable random samples grow larger, what generally happens to standard error of the mean?|It decreases|It always becomes exactly zero|It increases in direct proportion|It becomes the mean|For fixed population spread, SE scales as 1/√n.
capstone|A predicted output is 12 units and observed output is 15. What should a report do?|Report the 3-unit discrepancy and investigate limitations|Hide the observed value|Label the prediction as the observation|Conclude that every future result is 15|Transparent reporting identifies and investigates model error.
BANK,
    'science7' => <<<'BANK'
models|Which statement is an observation rather than an inference?|The liquid is blue.|The liquid is blue because it contains a special pigment.|The liquid will change color tomorrow.|Blue liquids must be cold.|The first statement records a visible property without explaining it.
particles|Why can a gas fill a container of any shape?|Its particles move freely in all directions.|Its particles are fixed in one position.|Its particles have no mass.|Its particles stop moving.|Gas particles move and spread through available space.
solutions|A 200 g salt solution contains 10 g salt. What is its percent by mass?|5%|10%|20%|2%|10/200×100=5%.
motion|A learner walks 20 m in 5 s. What is the average speed?|4 m/s|25 m/s|100 m/s|0.25 m/s|Distance divided by time.
energy|Feeling warmth from sunlight without touching the Sun mainly illustrates what process?|Radiation|Conduction|Convection of metal|Friction|Energy arrives through electromagnetic waves.
cells|Which labeled structure occurs in typical plant cells but not typical animal cells?|Chloroplast|Cell membrane|Nucleus|Cytoplasm|Chloroplasts carry out photosynthesis.
ecosystems|If insect consumers decrease greatly, what is one plausible food-web effect?|Birds feeding on them may have less food.|All producers immediately vanish.|Energy at each level doubles.|The food web no longer needs producers.|A decrease in prey can reduce food availability for predators.
weather|A cool sea breeze moves landward on a warm afternoon. Which explanation fits?|Land heats faster and warmer air rises over it.|Water stops evaporating.|Earth stops rotating.|The land has no atmosphere.|Temperature differences drive local air circulation.
space|What is the principal reason Earth's seasons occur?|Earth's tilted axis and orbit change sunlight angles and day length.|The Sun turns off in winter.|The Moon blocks sunlight for months.|Earth rotates at different speeds each month.|Axis tilt changes incoming sunlight through the year.
BANK,
    'science8' => <<<'BANK'
atoms|An atom has 8 protons and 10 neutrons. What is its atomic number?|8|10|18|2|Atomic number is proton count.
matter|Which is a physical change?|Water freezes.|Paper burns.|Iron rusts.|A new substance forms.|Freezing changes state without changing water's chemical identity.
forces|A 3 kg cart experiences a 12 N net force. What is its acceleration?|4 m/s²|36 m/s²|0.25 m/s²|15 m/s²|a=F/m=12/3.
light|A straw appears bent at the boundary of air and water because of what process?|Refraction|Reflection alone|Sound diffraction|Magnetic attraction|Light changes direction as its speed changes across the boundary.
digestion|Why do digestion and circulation work together?|Digestion makes nutrients absorbable and blood transports them.|Both create light.|Digestion pumps blood directly.|Circulation takes the place of food.|The systems cooperate to supply cells with nutrients.
heredity|For a simple modeled cross Aa × aa, what fraction of offspring genotypes are expected to be Aa?|1/2|1/4|3/4|All|A and a from one parent combine with a from the other.
biodiversity|Which observation suggests greater species richness for the stated samples?|Eight different species rather than three.|Eight individuals of the same species rather than nine.|A larger unknown sample without identities.|A higher temperature alone.|Richness counts distinct species.
earthquakes|Why can the same earthquake have different intensities at two locations?|Shaking effects depend on distance and local ground conditions.|It has two atomic numbers.|Its magnitude is different at every observer.|All locations have identical ground.|Intensity is location-specific shaking and damage.
storms|Which information helps a school evaluate flood risk beyond hazard alone?|Exposure of buildings and vulnerability of occupants.|The teacher's favorite color.|The shape of a classroom logo.|A student's private test score.|Risk combines hazard, exposure and vulnerability.
BANK,
    'science9' => <<<'BANK'
bonding|Diagram A shows two atoms sharing electron pairs. Which bonding type is modeled?|Covalent bonding|Ionic attraction only|Gravitational binding|Metallic lattice only|Covalent bonds involve shared electron pairs.
carbon|Which statement describes graphite and diamond?|They are different structural forms of carbon.|They contain different atomic numbers for carbon.|They are the same crystal structure.|Neither contains carbon.|They are allotropes of carbon.
quantities|Using approximately 18 g/mol for water, what is the mass of 2 mol?|36 g|9 g|18 g|2 g|Mass=moles×molar mass.
projectile|In an ideal diagram of a rolling ball after it leaves a level table, what is the direction of gravitational acceleration?|Downward|Horizontally forward|Upward|Zero everywhere|Gravity acts downward in the local model.
work|A force of 8 N acts along a 3 m displacement. How much work is done?|24 J|11 J|8/3 J|5 J|Work=force×displacement along force.
electricity|In a supplied circuit model, 12 V is applied across a 6 Ω resistor. What is the modeled current?|2 A|72 A|0.5 A|18 A|I=V/R=12/6.
systems|Which structure provides a large surface for gas exchange in the lungs?|Alveoli|Bones|Skin pigment|A nerve axon|Alveoli are small air sacs with exchange surfaces.
volcanoes|What is molten rock called after reaching Earth's surface?|Lava|Magma only|Lithosphere|Sediment|Lava is the surface term for molten rock.
climate|Which dataset best supports investigation of a climate trend?|Consistent temperature records over many years.|One afternoon's weather.|One anonymous opinion.|A single photograph with no date.|Climate describes long-term patterns.
BANK,
    'science10' => <<<'BANK'
tectonics|Where is new oceanic crust commonly formed?|At divergent ocean ridges|At every transform boundary|At all river mouths|At the center of every continent|Divergent spreading creates new oceanic crust.
waves|Which has the greater frequency in the electromagnetic spectrum?|Ultraviolet compared with visible red light|Radio compared with ultraviolet|Infrared compared with ultraviolet|All frequencies are equal|UV has higher frequency than visible red.
optics|What type of image does an ordinary plane mirror form?|Virtual and upright|Real and inverted|Real at the observer's eye only|No image|Reflected rays appear to originate behind the mirror.
electricity|A classroom generator model changes mechanical energy primarily into what form?|Electrical energy|Nuclear energy|Stored genetic information|Mass with no energy|A generator uses motion and electromagnetic induction.
coordination|Which example illustrates homeostasis?|Regulation of internal temperature around a range.|A permanent loss of all control.|Formation of mountain ranges.|Change in the atomic number of an element.|Homeostasis stabilizes internal conditions.
genetics|Which conclusion is justified by the information that DNA contains hereditary instructions?|Differences in inherited information can contribute to trait variation.|Every trait is unaffected by environment.|All individuals have identical traits.|DNA is identical to protein.|Genetic differences and environmental factors can affect traits.
evolution|Which kind of evidence can support common ancestry?|Homologous structures and genetic comparisons.|A species' name alone.|The geographic size of a country.|The teacher's personal preference.|Multiple independent evidence types support relatedness.
chemical|Why must chemical equations have matching atom counts before and after?|Atoms are conserved in ordinary chemical reactions.|Every product must be a liquid.|All coefficients must be one.|Atoms disappear when a symbol is written.|Balanced equations express conservation of mass.
gases|A fixed gas amount at constant temperature has volume 4 L at a pressure of 1 unit. If the pressure doubles in the ideal model, what volume results?|2 L|8 L|4 L|16 L|Boyle’s law gives pressure×volume constant.
BANK,
];

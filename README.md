# Optimizing-Talent-Acquisition-and-Onboarding-Vector-team-
**Project Title:** Optimizing Talent Acquisition and Onboarding

**Vectorian:**
1) Tang Yen Sin (Team Leader)
2) Lee Shy Woei
3) Keh Ban Ning
4) Tee Onn Min
5) Chong Ming Li

# Problem and Solution Summary
## Problem Statement:
**Use Case - HairCare2u**

Hiring and onboarding new talent, especially for specialized roles, is a time-consuming and inefficient process for SMEs like HairCare2U. As a growing business in the haircare and beauty retail sector, HairCare2U is expanding its digital operations and requires new hires across both technical and non-technical roles. However, the HR team faces **two major challenges**:

- **Recruitment Inefficiency** – HR struggles to identify best-fit candidates from a flood of resumes, many of which are unqualified. For example, a **Product Specialist** must demonstrate strong presentation skills and sales techniques, while a **Data Analyst** must possess technical skills such as SQL, Excel, and data visualization. Without a smart filtering tool, HR spends hours reviewing applications that do not meet the role’s requirements.

- **Onboarding Delays** – Once new employees are hired, they often spend several days figuring out tools, policies, and processes on their own. This slows down their ability to contribute and creates unnecessary frustration.

**Goal:** Create tools that streamline resume shortlisting, automate aspects of the onboarding experience(e.g., via web apps or chatbots), and help new employees get started faster

## Solution:

We propose an **AI-driven HR platform** that streamlines hiring and onboarding for SMEs like HairCare2u:

**HR Resume Parsing & Scoring:**
- Automates resume screening with a “fit score” based on required skills, experience, and education.
- Provides transparent insights into matched skills, missing skills, and candidate strengths.

**AI Onboarding Chatbot:**
- Interactive chatbot that guides new hires through role-specific onboarding steps.
- Answers FAQs from company documentation with cited sources.
- Includes progress tracking for HR to monitor onboarding completion in real-time.

**HR Analytics & Reporting Dashboard:**
- Visualizes recruitment funnel efficiency, application trends, department hiring success, and training completion rates.
- Provides actionable insights to optimize HR strategies and improve employee experience.

## Impact
- **Faster Hiring:** HR only reviews truly qualified candidates.
- **Better Role Matching:** Candidates are evaluated against the actual skills required for success in specific roles (e.g., Product Specialist vs Data Analyst).
- **Quicker Onboarding:** New employees can become productive within days instead of weeks, improving overall efficiency and employee satisfaction.
- **Improved Employee Experience** → Clearer guidance and faster integration boost satisfaction and retention.

# Technology Stack Used
**Frontend (Client-side)**
- **HTML** – for structure of web pages
- **CSS** – for styling and layout
- **JavaScript** (vanilla) – for interactivity 

**Backend (Server-side)**
- **PHP** – main server-side scripting language (.php files)
- **XAMPP** – local development environment (bundled with Apache, MySQL/MariaDB, PHP, and Perl)

**Database**
- **MySQL** (via XAMPP) – to store and manage application data

**APIs & Integrations**
- **Extracta.ai** – Resume parsing API
- **OpenAI GPT-4o** – Chatbot / conversational AI integration

**Others (support tools)**
- **Apache** (from XAMPP) – Web server running your PHP
- **phpMyAdmin** (from XAMPP) – GUI tool to manage MySQL

Our website runs on **PHP + MySQL (XAMPP)** with a **vanilla HTML/CSS/JS frontend**, and integrates with **Extracta.ai** and **OpenAI GPT-4o APIs**.

# Setup Instruction
_Below is the instructions on installing and setting up Vector:_

1. Download & install Visual Studio Code (as code editor)
2. Download & install XAMPP
   -Install it and start the **Apache** and **MySQL** services from the XAMPP control panel.
3. Clone the repository
   -git clone 

# Reflection on challenges and learnings

During the development of this solution, we encountered **several challenges** that shaped our learning experience:

- **Understanding SME Pain Points** – It was challenging to balance the unique needs of SMEs like HairCare2U, which require both technical recruitment (e.g., Data Analysts) and non-technical recruitment (e.g., Product Specialists). We learned the importance of tailoring tools to support diverse hiring scenarios.
- **Designing Transparent AI Tools** – Building resume parsing and scoring features raised questions of fairness and bias. We realized that providing explainable results (why a candidate scored high or low) is critical for HR trust and adoption.
- **Streamlining Onboarding Workflows** – Onboarding processes often vary by role and department. Designing a chatbot that supports role-based checklists taught us how important personalization is for efficiency and employee satisfaction.
- **Data-Driven HR Insights** – Developing analytics features pushed us to think beyond automation and into strategic HR decision-making. We learned how metrics like hiring funnel conversion, application trends, and training completion can directly improve business performance.

## Key Learnings
- **Empathy for End Users** → Building HR tools isn’t just about automation, but about improving the daily experience of recruiters and employees.

- **Transparency Builds Trust** → AI systems must explain their decisions to be useful in real-world HR contexts.

- **Scalability Matters** → Solutions must adapt to different roles, departments, and growth stages of SMEs.

- **Data Creates Value** → Insights from analytics empower HR teams to move from reactive problem-solving to proactive workforce planning.

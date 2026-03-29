# SkateShop

**SkateShop** is a comprehensive web platform developed as a qualification exam project at Liepāja State Technical School. It uniquely combines the features of a traditional e-commerce store with a community-driven marketplace specifically tailored for the skateboarding community in Latvia. The system offers a secure, modern, and reliable space for trading skateboards, wheels, bearings, clothing, and accessories.

---

## Project Previews


### Main Page
<img width="1620" height="850" alt="skateshop-mainpage" src="https://github.com/user-attachments/assets/52eac6a5-a804-47bf-b7b2-997f686fb61a" />


### Shop (E-Commerce Catalog)
<img width="1134" height="852" alt="skateshop-shop" src="https://github.com/user-attachments/assets/248a054a-08e5-4c64-927b-613813ef8187" />

### Marketplace
<img width="1134" height="776" alt="skateshop-market" src="https://github.com/user-attachments/assets/328a7957-10f7-483c-b908-fad025aae6b0" />


### Admin Panel
<img width="1650" height="852" alt="skateshop-admin" src="https://github.com/user-attachments/assets/a446c915-5ebd-4302-8c71-b5f92353a823" />

---

## Core Features

### E-Commerce & Shopping Experience
* **Dynamic Product Catalog**: Browse products with advanced full-text search and filtering options by brand, price, and other parameters.
* **Smart Shopping Cart**: Add items, update quantities with stock validation, and delete products easily.
* **Order Processing**: Secure checkout with data validation and integration with an external payment API returning success or failure states.
* **Automated Confirmations**: Automated personalized HTML email confirmations are sent to users upon successful purchase.

### Community Marketplace
* **User Selling Hub**: Authenticated users can create seller profiles and submit products to the marketplace.
* **Reputation System**: Peer-to-peer trust is promoted through rating and reviewing sellers based on previous interactions.
* **Seller Verification**: A built-in flow where users request verification to get a trusted checkmark icon from administrators.

### Social & Interaction
* **Reviews & Ratings**: Users can leave reviews (up to 500 characters) for products, which are moderated before appearing publicly.
* **Videos & QnA Sections**: Includes space for community video content and a Question and Answer section to drive engagement.
* **Multilingual Platform**: Full UI translation support between Latvian, English, and German without requiring page reloads.

### Security & Reliability
* **Data Protection**: Encrypted passwords, prevention against standard SQL injection attacks, and strict role-based access control.
* **Account Safety**: Temporary account lockouts after multiple failed authentication attempts.
* **Responsive Design**: Fully responsive interface adapted for desktops, tablets, and smartphones.

---

## User Roles & Access Levels

The platform differentiates permissions through four distinct user roles:

1. **Unauthenticated User (Visitor)**
   * Can browse the catalog, use search, and filters.
   * Can view video and QnA sections.
   * Cannot place orders, add content, or submit reviews.
2. **Authenticated User (Client/Seller)**
   * Can add products to the cart and place orders.
   * Can manage personal profile information.
   * Can add and edit their own product reviews.
   * Can submit marketplace items and upload community videos.
3. **Moderator**
   * Responsible for maintaining platform quality and content moderation.
   * Can review, edit, and moderate user reviews and check published products.
   * Answers client messages and approves or rejects community video submissions.
4. **Administrator**
   * Oversees full platform operations, security, and user management.
   * Approves seller accounts and manages existing content.

---

## Tech Stack

The system relies on lightweight open-source technologies for direct control and maintainability:

* **Frontend**: HTML5, CSS3, and Vanilla JavaScript.
* **Backend**: PHP (handling server requests, session handling, and database communication).
* **Database**: MySQL (handling large volume data scaling, ensuring integrity for e-commerce transactions).
* **Visualizations**: Chart.js library for drawing interactive dynamic statistic representations in the backend.
* **Tools**: GitHub Desktop and Git for version control, and Visual Studio Code for development.

---


// Import the functions you need from the SDKs you need
import { initializeApp } from "https://www.gstatic.com/firebasejs/12.15.0/firebase-app.js";
import { getAnalytics } from "https://www.gstatic.com/firebasejs/12.15.0/firebase-analytics.js";
// TODO: Add SDKs for Firebase products that you want to use
// https://firebase.google.com/docs/web/setup#available-libraries

// Your web app's Firebase configuration
// For Firebase JS SDK v7.20.0 and later, measurementId is optional
const firebaseConfig = {
    apiKey: "AIzaSyCTPY28gYNHV6TS_YVafSGneXvTvb7ryMA",
    authDomain: "jendarestaurant.firebaseapp.com",
    projectId: "jendarestaurant",
    storageBucket: "jendarestaurant.firebasestorage.app",
    messagingSenderId: "518545519468",
    appId: "1:518545519468:web:cc09a476b011f1a379b949",
    measurementId: "G-RCLGV4BLMS"
};

// Initialize Firebase
const app = initializeApp(firebaseConfig);
const analytics = getAnalytics(app);

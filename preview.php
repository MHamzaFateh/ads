<?php
require_once 'config.php';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>AI-Powered Targeted Advertising</title>
    <!-- Face-API.js -->
    <script src="https://cdn.jsdelivr.net/npm/face-api.js@0.22.2/dist/face-api.min.js"></script>
    <style>
        body {
            margin: 0;
            padding: 0;
            overflow: hidden;
            font-family: Arial, sans-serif;
        }
        #videoElement {
            position: fixed;
            right: 20px;
            bottom: 20px;
            width: 320px;
            height: 240px;
            border-radius: 10px;
            z-index: 100;
            border: 3px solid #fff;
            box-shadow: 0 0 20px rgba(0, 0, 0, 0.5);
        }
        #adContainer {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background-color: #000;
            z-index: 1;
        }
        #adVideo {
            width: 100%;
            height: 100%;
            border: none;
        }
        #detectionInfo {
            position: fixed;
            bottom: 270px;
            right: 20px;
            background: rgba(0, 0, 0, 0.7);
            color: white;
            padding: 10px;
            border-radius: 5px;
            z-index: 100;
            max-width: 300px;
        }
        #aiMessage {
            position: fixed;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
            background: rgba(0, 0, 0, 0.8);
            color: #0f0;
            padding: 20px 40px;
            border-radius: 10px;
            font-size: 24px;
            z-index: 1000;
            display: none;
            text-align: center;
            border: 2px solid #0f0;
            animation: pulse 2s infinite;
        }
        @keyframes pulse {
            0% { transform: translate(-50%, -50%) scale(1); }
            50% { transform: translate(-50%, -50%) scale(1.05); }
            100% { transform: translate(-50%, -50%) scale(1); }
        }
    </style>
</head>
<body>
    <div id="adContainer">
        <div id="youtubePlayer"></div>
    </div>
    <div style="position: fixed; right: 20px; bottom: 20px; width: 320px; height: 240px; z-index: 90; overflow: hidden; border-radius: 8px; box-shadow: 0 0 10px rgba(0,0,0,0.3);">
        <video id="videoElement" autoplay playsinline style="position: absolute; width: 100%; height: 100%; object-fit: cover;"></video>
        <canvas id="output" style="position: absolute; width: 100%; height: 100%; background: transparent;"></canvas>
    </div>
    <div id="detectionInfo">Detecting faces...</div>
    <div id="aiMessage"></div>

    <script>
        // Initialize ads object
        const ads = {
            default: null, // Will be set from database
            female: {
                child: [],
                teenage: [],
                young: [],
                adult: []
            },
            male: {
                child: [],
                teenage: [],
                young: [],
                adult: []
            },
            all: {
                child: [],
                teenage: [],
                young: [],
                adult: []
            }
        };
        
        // Fallback default video (Rick Astley - Never Gonna Give You Up)
        const FALLBACK_DEFAULT_VIDEO = 'dQw4w9WgXcQ';

        // Load ads from PHP
        <?php
        try {
            $db = getDB();
            
            // First, get the default ad if it exists
            $defaultAd = $db->query('SELECT video_link FROM ads WHERE is_default = 1 LIMIT 1')->fetch(PDO::FETCH_ASSOC);
            
            // Then get all ads for targeting
            $stmt = $db->query('SELECT * FROM ads');
            $dbAds = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            // Output the default ad video ID if it exists
            if ($defaultAd && !empty($defaultAd['video_link'])) {
                echo "ads.default = '" . addslashes($defaultAd['video_link']) . "';";
            } else {
                echo "ads.default = null;";
            }
            
            if (!empty($dbAds)) {
                foreach ($dbAds as $ad) {
                    $videoId = $ad['video_link'];
                    $gender = strtolower($ad['gender']);
                    $ageGroups = explode(',', $ad['age_groups']);
                    
                    // Process each age group for this ad
                    foreach ($ageGroups as $ageGroup) {
                        $ageGroup = trim($ageGroup);
                        if (in_array($ageGroup, ['child', 'teenage', 'young', 'adult'])) {
                            echo "if (ads.$gender && ads.$gender.$ageGroup) {";
                            echo "  ads.$gender.$ageGroup.push('" . addslashes($videoId) . "');";
                            echo "}";
                        }
                    }
                }
            }
        } catch (PDOException $e) {
            error_log('Error loading ads: ' . $e->getMessage());
            echo "ads.default = null;"; // Ensure default is set even if there's an error
        }
        ?>

        // DOM elements
        const videoElement = document.getElementById('videoElement');
        const canvasElement = document.getElementById('output');
        const canvasCtx = canvasElement.getContext('2d');
        const detectionInfo = document.getElementById('detectionInfo');
        const youtubePlayer = document.getElementById('youtubePlayer');
        const aiMessage = document.getElementById('aiMessage');

        // YouTube Player API
        let player;
        let isYouTubeAPILoaded = false;
        
        // Make onYouTubeIframeAPIReady globally available
        window.onYouTubeIframeAPIReady = function() {
            isYouTubeAPILoaded = true;
            player = new YT.Player('youtubePlayer', {
                width: '100%',
                height: '100%',
                playerVars: {
                    'autoplay': 1,
                    'controls': 0,
                    'disablekb': 1,
                    'fs': 0,
                    'loop': 1,
                    'modestbranding': 1,
                    'playsinline': 1,
                    'rel': 0,
                    'showinfo': 0,
                    'mute': 1
                },
                events: {
                    'onReady': onPlayerReady,
                    'onStateChange': onPlayerStateChange
                }
            });
        };

        function onPlayerReady(event) {
            // Player is ready
            playDefaultAd();
        }

        function onPlayerStateChange(event) {
            if (event.data === YT.PlayerState.ENDED) {
                isAdPlaying = false;
                // Process any pending detection after a small delay
                setTimeout(processPendingDetection, 1000);
            }
        }

        // Load YouTube IFrame API with error handling
        function loadYouTubeIframeAPI() {
            return new Promise((resolve, reject) => {
                const tag = document.createElement('script');
                tag.src = 'https://www.youtube.com/iframe_api';
                tag.onload = () => {
                    // Wait for the global YT object to be available
                    const checkYT = setInterval(() => {
                        if (window.YT && window.YT.Player) {
                            clearInterval(checkYT);
                            resolve();
                        }
                    }, 100);
                };
                tag.onerror = () => {
                    reject(new Error('Failed to load YouTube IFrame API'));
                };
                const firstScriptTag = document.getElementsByTagName('script')[0];
                firstScriptTag.parentNode.insertBefore(tag, firstScriptTag);
            });
        }

        // Initialize YouTube player after API is loaded
        loadYouTubeIframeAPI().catch(error => {
            console.error('Error loading YouTube API:', error);
            detectionInfo.textContent = 'Error loading YouTube player. Please refresh the page.';
        });

        // Face detection and ad state variables
        let lastDetection = {
            gender: null,
            ageGroup: null,
            timestamp: 0,
            confidence: 0
        };
        let currentAd = null;
        let isAdPlaying = false;
        let pendingDetection = null;
        let modelsLoaded = false;
        
        // Check if YouTube API is loaded before starting face detection
        async function checkYouTubeAPILoaded() {
            const maxAttempts = 30; // 30 attempts with 100ms interval = 3 seconds max wait
            let attempts = 0;
            
            return new Promise((resolve, reject) => {
                const checkInterval = setInterval(() => {
                    if (isYouTubeAPILoaded) {
                        clearInterval(checkInterval);
                        resolve();
                    } else if (attempts >= maxAttempts) {
                        clearInterval(checkInterval);
                        reject(new Error('YouTube API failed to load'));
                    }
                    attempts++;
                }, 100);
            });
        }

        // Load face-api.js models
        async function loadModels() {
            try {
                await Promise.all([
                    faceapi.nets.tinyFaceDetector.loadFromUri('./models'),
                    faceapi.nets.ageGenderNet.loadFromUri('./models')
                ]);
                modelsLoaded = true;
                console.log('Face detection models loaded');
            } catch (error) {
                console.error('Error loading face detection models:', error);
                throw error; // Re-throw to be caught by the caller
            }
        }

        async function initializeFaceDetection() {
            try {
                await checkYouTubeAPILoaded();
                console.log('YouTube API loaded, initializing face detection...');
                detectionInfo.textContent = 'Initializing camera...';
                
                // Set canvas dimensions to match video
                canvasElement.width = 320;
                canvasElement.height = 240;
                
                // Load face detection models first
                console.log('Loading models...');
                detectionInfo.textContent = 'Loading face detection models...';
                await loadModels();
                
                // Start video stream
                if (navigator.mediaDevices && navigator.mediaDevices.getUserMedia) {
                    console.log('Requesting camera access...');
                    detectionInfo.textContent = 'Requesting camera access...';
                    
                    const stream = await navigator.mediaDevices.getUserMedia({
                        video: {
                            width: { ideal: 320 },
                            height: { ideal: 240 },
                            facingMode: 'user'  // Changed from { exact: 'user' } to be more permissive
                        },
                        audio: false
                    });
                    
                    console.log('Camera access granted, setting up video element...');
                    videoElement.srcObject = stream;
                    
                    // Wait for video to be ready
                    await new Promise((resolve, reject) => {
                        videoElement.onloadedmetadata = () => {
                            console.log('Video metadata loaded, starting playback...');
                            videoElement.play().then(() => {
                                console.log('Video playback started');
                                resolve();
                            }).catch(err => {
                                console.error('Error starting video playback:', err);
                                reject(err);
                            });
                        };
                        
                        videoElement.onerror = (err) => {
                            console.error('Video element error:', err);
                            reject(err);
                        };
                        
                        // Add timeout for video to load
                        setTimeout(() => {
                            if (videoElement.readyState >= 2) { // HAVE_CURRENT_DATA
                                resolve();
                            } else {
                                console.warn('Video loading taking longer than expected, continuing anyway...');
                                resolve();
                            }
                        }, 2000);
                    });
                    
                    // Start face detection
                    console.log('Starting face detection...');
                    detectionInfo.textContent = 'Starting face detection...';
                    detectFaces();
                    
                } else {
                    throw new Error('getUserMedia is not supported by this browser');
                }
            } catch (err) {
                console.error('Error initializing face detection:', err);
                detectionInfo.textContent = `Error: ${err.message || 'Failed to initialize camera'}`;
                
                // Try to recover after a delay
                setTimeout(initializeFaceDetection, 3000);
            }
        }

        // Process face detection results
        async function detectFaces() {
            if (!modelsLoaded) {
                console.warn('Models not loaded yet, retrying...');
                setTimeout(detectFaces, 1000);
                return;
            }

            // Check if video is ready
            if (videoElement.readyState < 2) { // 2 = HAVE_CURRENT_DATA
                console.warn('Video not ready, retrying...');
                setTimeout(detectFaces, 500);
                return;
            }

            try {
                const options = new faceapi.TinyFaceDetectorOptions({
                    inputSize: 320,  // Match our video size
                    scoreThreshold: 0.3  // Lower threshold to detect more faces
                });

                // Perform face detection
                const detections = await faceapi
                    .detectAllFaces(videoElement, options)
                    .withAgeAndGender();

                // Clear canvas
                canvasCtx.clearRect(0, 0, canvasElement.width, canvasElement.height);
                
                // Draw detections on canvas for debugging
                const detectionsForDraw = faceapi.resizeResults(detections, {
                    width: videoElement.videoWidth,
                    height: videoElement.videoHeight
                });
                faceapi.draw.drawDetections(canvasElement, detectionsForDraw);

                if (detections && detections.length > 0) {
                    const detection = detections[0]; // Get the most prominent face
                    const gender = detection.gender === 'male' ? 'male' : 'female';
                    const age = detection.age;
                    let ageGroup = 'adult'; // Default age group
                    
                    // Categorize age group
                    if (age < 18) ageGroup = 'young';
                    else if (age < 30) ageGroup = 'young';
                    else if (age < 50) ageGroup = 'adult';
                    else ageGroup = 'senior';
                    
                    const confidence = detection.detection.score;
                    const now = Date.now();
                    
                    // Update detection info
                    detectionInfo.textContent = `Detected: ${gender}, ${Math.round(age)} years (${ageGroup}) [${Math.round(confidence * 100)}%]`;
                    
                    // Update ad if needed
                    if (shouldSwitchAd(gender, ageGroup, confidence, now)) {
                        console.log(`Updating ad for ${gender}, ${ageGroup}`);
                        updateAd(gender, ageGroup);
                    }
                } else {
                    detectionInfo.textContent = 'No face detected';
                    console.log('No face detected, switching to default ad');
                    // Always switch to default ad when no face is detected
                    lastDetection = { gender: 'default', ageGroup: 'default', confidence: 1, timestamp: Date.now() };
                    updateAd('default', 'default');
                }
            } catch (error) {
                console.error('Error during face detection:', error);
                detectionInfo.textContent = 'Error in face detection';
                
                // Try to recover after a short delay
                setTimeout(detectFaces, 1000);
                return;
            }
            
            // Continue detection with requestAnimationFrame for smooth updates
            requestAnimationFrame(detectFaces);
        }

        function shouldSwitchAd(newGender, newAgeGroup, confidence, timestamp) {
            // Always switch if no ad is playing
            if (!isAdPlaying) {
                console.log(`No ad playing, switching to new ad (${newGender}, ${newAgeGroup}, ${Math.round(confidence * 100)}%)`);
                lastDetection = { gender: newGender, ageGroup: newAgeGroup, confidence, timestamp };
                return true;
            }
            
            // If we're currently showing the default ad, only switch if we have high confidence in the new detection
            if (lastDetection.gender === 'default') {
                if (confidence >= 0.7) {
                    console.log(`Switching from default to detected (${newGender}, ${newAgeGroup}, ${Math.round(confidence * 100)}%)`);
                    lastDetection = { gender: newGender, ageGroup: newAgeGroup, confidence, timestamp };
                    return true;
                } else {
                    console.log(`Keeping default ad - new detection confidence too low (${Math.round(confidence * 100)}% < 70%)`);
                    return false;
                }
            }
            
            // Don't switch too often (minimum 5 seconds between switches)
            const timeSinceLastSwitch = timestamp - lastDetection.timestamp;
            if (timeSinceLastSwitch < 5000) {
                return false;
            }
            
            // If we already have a pending detection, update it if this one is better
            if (pendingDetection) {
                if (confidence > pendingDetection.confidence) {
                    console.log('Updating pending detection with better confidence');
                    pendingDetection = { gender: newGender, ageGroup: newAgeGroup, confidence, timestamp };
                }
                return false;
            }
            
            // If this detection is significantly better than the current one (20% more confident)
            const confidenceThreshold = lastDetection.confidence * 1.2;
            if (confidence > confidenceThreshold) {
                console.log(`Better detection found (${Math.round(confidence * 100)}% > ${Math.round(confidenceThreshold * 100)}%)`);
                pendingDetection = { gender: newGender, ageGroup: newAgeGroup, confidence, timestamp };
                
                // Schedule the switch after a short delay
                setTimeout(() => {
                    if (pendingDetection) {
                        console.log('Processing pending detection after delay');
                        const { gender, ageGroup } = pendingDetection;
                        pendingDetection = null;
                        updateAd(gender, ageGroup);
                    }
                }, 1000);
            }
            
            return false;
        }

        function processPendingDetection() {
            if (pendingDetection && !isAdPlaying) {
                const { gender, ageGroup } = pendingDetection;
                pendingDetection = null;
                updateAd(gender, ageGroup);
            }
        }

        function updateAd(gender, ageGroup) {
            // Don't update if we're already showing this ad
            if (isAdPlaying && currentAd && currentAd.gender === gender && currentAd.ageGroup === ageGroup) {
                return;
            }
            
            // Try to find a matching ad
            let adUrl = null;
            
            // First try exact match
            if (ads[gender] && ads[gender][ageGroup] && ads[gender][ageGroup].length > 0) {
                const adsForDemographic = ads[gender][ageGroup];
                adUrl = adsForDemographic[Math.floor(Math.random() * adsForDemographic.length)];
            }
            // Then try gender match with 'all' age group
            else if (ads[gender] && ads[gender].all && ads[gender].all.length > 0) {
                const adsForGender = ads[gender].all;
                adUrl = adsForGender[Math.floor(Math.random() * adsForGender.length)];
            }
            // Fall back to default ad
            else {
                playDefaultAd();
                return;
            }
            
            // Play the selected ad
            playAd(adUrl, gender, ageGroup);
        }

        function playAd(videoId, gender, ageGroup) {
            if (!player) {
                console.error('YouTube player not ready');
                return;
            }
            
            // Extract video ID from URL if it's a full YouTube URL
            let videoIdToPlay = videoId;
            
            // If it's a full URL, extract the video ID
            if (videoId.includes('youtube.com') || videoId.includes('youtu.be')) {
                const youtubeRegex = /(?:youtube\.com\/(?:[^\/]+\/.+\/|(?:v|e(?:mbed)?)\/|.*[?&]v=)|youtu\.be\/)([^"&?\/\s]{11})/i;
                const match = videoId.match(youtubeRegex);
                if (match && match[1]) {
                    videoIdToPlay = match[1];
                }
            }
            
            try {
                // Make sure the video ID is valid (11 characters, no special characters)
                if (!/^[a-zA-Z0-9_-]{11}$/.test(videoIdToPlay)) {
                    console.error('Invalid YouTube video ID format:', videoIdToPlay);
                    playDefaultAd();
                    return;
                }
                
                player.loadVideoById({
                    videoId: videoIdToPlay,
                    startSeconds: 0,
                    suggestedQuality: 'highres'
                });
                
                currentAd = { gender, ageGroup };
                isAdPlaying = true;
                
                // Show AI message
                showAIMessage(`Showing ad for ${gender}, ${ageGroup}`);
                
                console.log('Playing ad:', videoIdToPlay, 'for', gender, ageGroup);
            } catch (error) {
                console.error('Error playing video:', error);
                playDefaultAd();
            }
        }

        function playDefaultAd() {
            try {
                // Try to play the default ad if it exists
                if (ads.default) {
                    playAd(ads.default, 'default', 'default');
                    return;
                }
                
                // If no default ad is set, try to find any ad to play
                const allGenders = ['female', 'male', 'all'];
                const allAgeGroups = ['child', 'teenage', 'young', 'adult'];
                
                for (const gender of allGenders) {
                    for (const ageGroup of allAgeGroups) {
                        const adsForGroup = ads[gender]?.[ageGroup];
                        if (adsForGroup && adsForGroup.length > 0) {
                            const randomIndex = Math.floor(Math.random() * adsForGroup.length);
                            playAd(adsForGroup[randomIndex], gender, ageGroup);
                            return;
                        }
                    }
                }
                
                // If no ads are found at all, play the fallback video
                playAd(FALLBACK_DEFAULT_VIDEO, 'fallback', 'fallback');
            } catch (error) {
                console.error('Error in playDefaultAd:', error);
                // Try again after a short delay if there was an error
                setTimeout(playDefaultAd, 1000);
            }
        }

        function getRandomAd(gender, ageGroup) {
            try {
                // First try to get an ad for the exact gender and age group
                const adsForGroup = ads[gender]?.[ageGroup] || [];
                if (adsForGroup.length > 0) {
                    const randomIndex = Math.floor(Math.random() * adsForGroup.length);
                    return adsForGroup[randomIndex];
                }
                
                // If no specific ad found, try to find any ad for this gender
                const allAgeGroups = ['child', 'teenage', 'young', 'adult'];
                for (const ag of allAgeGroups) {
                    const altAds = ads[gender]?.[ag] || [];
                    if (altAds.length > 0) {
                        const randomIndex = Math.floor(Math.random() * altAds.length);
                        return altAds[randomIndex];
                    }
                }
                
                // Try the 'all' gender for any age group
                for (const ag of allAgeGroups) {
                    const altAds = ads.all?.[ag] || [];
                    if (altAds.length > 0) {
                        const randomIndex = Math.floor(Math.random() * altAds.length);
                        return altAds[randomIndex];
                    }
                }
                
                // If we have a default ad set, use it
                if (ads.default) {
                    return ads.default;
                }
                
                // As a last resort, return the fallback default video
                return FALLBACK_DEFAULT_VIDEO;
            } catch (error) {
                console.error('Error in getRandomAd:', error);
                return FALLBACK_DEFAULT_VIDEO; // Return fallback if there's an error
            }
        }

        function showAIMessage(text) {
            aiMessage.textContent = text;
            aiMessage.style.display = 'block';
            
            // Hide message after 3 seconds
            setTimeout(() => {
                aiMessage.style.display = 'none';
            }, 3000);
        }

        // Start the application when the page loads
        window.onload = initializeFaceDetection;
    </script>
</body>
</html>

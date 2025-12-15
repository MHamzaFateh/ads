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
                // Check for stable detection after ad ends
                setTimeout(() => {
                    if (currentStableDetection && currentStableDetection.gender !== 'default') {
                        updateAd(currentStableDetection.gender, currentStableDetection.ageGroup);
                    } else {
                        checkForStableDetection();
                    }
                }, 1000);
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
        let currentAd = null;
        let isAdPlaying = false;
        let modelsLoaded = false;
        
        // Stabilized detection system variables
        let detectionHistory = []; // Array to store recent detections
        let currentStableDetection = null; // The stabilized detection we're using
        let minAdPlayDuration = 10000; // Minimum 10 seconds before switching (adjust as needed)
        let lastAdSwitchTime = 0; // Track when we last switched ads
        let detectionVoteThreshold = 5; // Require 5 consistent detections before switching
        let noFaceTimeout = null; // Handle "no face" with delay
        let detectionInterval = 500; // Detection interval in milliseconds (slower = less flickering)
        
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

        // Process face detection results with stabilized voting system
        async function detectFaces() {
            if (!modelsLoaded) {
                console.warn('Models not loaded yet, retrying...');
                setTimeout(detectFaces, 1000);
                return;
            }

            // Check if video is ready
            if (videoElement.readyState < 2) { // 2 = HAVE_CURRENT_DATA
                console.warn('Video not ready, retrying...');
                setTimeout(detectFaces, detectionInterval);
                return;
            }

            try {
                const options = new faceapi.TinyFaceDetectorOptions({
                    inputSize: 320,  // Match our video size
                    scoreThreshold: 0.5  // Increased from 0.3 for better accuracy
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
                    
                    // Fixed age group categorization
                    if (age < 13) ageGroup = 'child';
                    else if (age < 20) ageGroup = 'teenage';
                    else if (age < 36) ageGroup = 'young';
                    else ageGroup = 'adult';
                    
                    const confidence = detection.detection.score;
                    
                    // Clear any pending "no face" timeout
                    if (noFaceTimeout) {
                        clearTimeout(noFaceTimeout);
                        noFaceTimeout = null;
                    }
                    
                    // Update detection info
                    detectionInfo.textContent = `Detected: ${gender}, ${Math.round(age)} years (${ageGroup}) [${Math.round(confidence * 100)}%]`;
                    
                    // Only process high-confidence detections
                    if (confidence >= 0.6) {
                        addDetectionToHistory(gender, ageGroup, confidence);
                        checkForStableDetection();
                    } else {
                        detectionInfo.textContent += ' (low confidence)';
                    }
                } else {
                    detectionInfo.textContent = 'No face detected';
                    
                    // Don't immediately switch - wait 3 seconds of no face detection
                    if (!noFaceTimeout) {
                        noFaceTimeout = setTimeout(() => {
                            console.log('No face detected for 3 seconds, switching to default');
                            clearDetectionHistory();
                            switchToDefaultAd();
                        }, 3000);
                    }
                }
            } catch (error) {
                console.error('Error during face detection:', error);
                detectionInfo.textContent = 'Error in face detection';
                
                // Try to recover after a short delay
                setTimeout(detectFaces, 1000);
                return;
            }
            
            // Continue detection at slower interval (500ms) instead of every frame
            setTimeout(detectFaces, detectionInterval);
        }

        // Add detection to history for voting system
        function addDetectionToHistory(gender, ageGroup, confidence) {
            const now = Date.now();
            const detection = { gender, ageGroup, confidence, timestamp: now };
            
            // Add to history
            detectionHistory.push(detection);
            
            // Keep only last 10 detections (last ~5 seconds at 500ms intervals)
            if (detectionHistory.length > 10) {
                detectionHistory.shift();
            }
            
            // Remove old detections (older than 3 seconds)
            const threeSecondsAgo = now - 3000;
            detectionHistory = detectionHistory.filter(d => d.timestamp > threeSecondsAgo);
        }

        // Check for stable detection using voting system
        function checkForStableDetection() {
            if (detectionHistory.length < detectionVoteThreshold) {
                return; // Not enough detections yet
            }
            
            // Count votes for each demographic combination
            const votes = {};
            detectionHistory.forEach(d => {
                const key = `${d.gender}_${d.ageGroup}`;
                if (!votes[key]) {
                    votes[key] = { count: 0, avgConfidence: 0, gender: d.gender, ageGroup: d.ageGroup };
                }
                votes[key].count++;
                votes[key].avgConfidence = (votes[key].avgConfidence * (votes[key].count - 1) + d.confidence) / votes[key].count;
            });
            
            // Find the most voted detection
            let maxVotes = 0;
            let winningDetection = null;
            
            for (const key in votes) {
                if (votes[key].count > maxVotes) {
                    maxVotes = votes[key].count;
                    winningDetection = votes[key];
                }
            }
            
            // Require at least detectionVoteThreshold votes (e.g., 5 out of 10)
            if (maxVotes >= detectionVoteThreshold && winningDetection) {
                const newDetection = {
                    gender: winningDetection.gender,
                    ageGroup: winningDetection.ageGroup,
                    confidence: winningDetection.avgConfidence,
                    timestamp: Date.now()
                };
                
                // Check if this is different from current stable detection
                if (!currentStableDetection || 
                    currentStableDetection.gender !== newDetection.gender ||
                    currentStableDetection.ageGroup !== newDetection.ageGroup) {
                    
                    // Check minimum ad play duration
                    const timeSinceLastSwitch = Date.now() - lastAdSwitchTime;
                    if (timeSinceLastSwitch >= minAdPlayDuration || !isAdPlaying) {
                        console.log(`Stable detection: ${newDetection.gender}, ${newDetection.ageGroup} (${maxVotes} votes, ${Math.round(newDetection.confidence * 100)}% confidence)`);
                        currentStableDetection = newDetection;
                        lastAdSwitchTime = Date.now();
                        updateAd(newDetection.gender, newDetection.ageGroup);
                    } else {
                        console.log(`Waiting for minimum ad duration (${Math.round((minAdPlayDuration - timeSinceLastSwitch) / 1000)}s remaining)`);
                    }
                }
            }
        }

        // Clear detection history
        function clearDetectionHistory() {
            detectionHistory = [];
            currentStableDetection = null;
        }

        // Switch to default ad
        function switchToDefaultAd() {
            currentStableDetection = { gender: 'default', ageGroup: 'default', confidence: 1, timestamp: Date.now() };
            const timeSinceLastSwitch = Date.now() - lastAdSwitchTime;
            if (timeSinceLastSwitch >= minAdPlayDuration || !isAdPlaying) {
                lastAdSwitchTime = Date.now();
                playDefaultAd();
            }
        }

        function updateAd(gender, ageGroup) {
            // Don't update if we're already showing this exact ad
            if (isAdPlaying && currentAd && currentAd.gender === gender && currentAd.ageGroup === ageGroup) {
                return;
            }
            
            // Handle default ad case
            if (gender === 'default' || ageGroup === 'default') {
                playDefaultAd();
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
            // Try 'all' gender with specific age group
            else if (ads.all && ads.all[ageGroup] && ads.all[ageGroup].length > 0) {
                const adsForAgeGroup = ads.all[ageGroup];
                adUrl = adsForAgeGroup[Math.floor(Math.random() * adsForAgeGroup.length)];
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

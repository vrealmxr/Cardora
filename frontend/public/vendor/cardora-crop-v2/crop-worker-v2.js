/* global importScripts, cv, CardoraAutoCrop */
importScripts('/vendor/opencv-4.10.0/opencv.js', '/vendor/cardora-crop-v2/card-geometry-v2.js');
const ready = new Promise(resolve => {
  if (cv.Mat) resolve();
  else cv.onRuntimeInitialized = () => resolve();
});
self.onmessage = async event => {
  try {
    await ready;
    const result = CardoraAutoCrop.prepare(cv, event.data);
    self.postMessage(result, result.data ? [result.data.buffer] : []);
  } catch { self.postMessage({ status: 'unavailable' }); }
};

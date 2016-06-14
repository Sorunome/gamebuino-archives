import os,sys
if os.geteuid() != 0:
	print('Sorry, but i need root!')
	sys.exit(1)

import json,pwd,grp,shutil,subprocess,traceback,time,threading

PATH = os.path.dirname(os.path.abspath(__file__))+'/'
UID = -1
GID = -1
with open(PATH+'settings.json') as f:
	config = json.load(f)

class Chroot_jail(threading.Thread):
	STATE_UNREADY = 0
	STATE_READY = 1
	STATE_RUNNING = 2
	STATE_DONE = 3
	state = 0
	sandbox_template_path = PATH+'sandbox/template'
	commands = []
	success = False
	mount_binds = ['random','urandom','null','zero']
	
	def __init__(self,i):
		threading.Thread.__init__(self)
		self.i = i
		self.sandbox_path = PATH+'sandbox/box_'+str(i)
		self.state = self.STATE_UNREADY
	def makeTemplate(self):
		print('Sandbox template absent, building...')
		if os.path.isdir(self.sandbox_template_path):
			shutil.rmtree(self.sandbox_template_path)
		
		if subprocess.call(['mkarchroot',self.sandbox_template_path]+config['packages']) != 0:
			return False
		os.makedirs(self.sandbox_template_path+'/build/bin',0o755)
		os.chown(self.sandbox_template_path+'/build',UID,GID)
		os.chown(self.sandbox_template_path+'/build/bin',UID,GID)
		for t in self.mount_binds:
			open(self.sandbox_template_path+'/dev/'+t,'a').close()
		
		return True
	def build_box(self):
		print('Building sandbox box_'+str(self.i)+' ...')
		try:
			if self.state == self.STATE_READY:
				return True
			if self.state != self.STATE_UNREADY:
				return False
			
			if not os.path.isdir(PATH+'sandbox/template'):
				if not self.makeTemplate():
					return False
			if os.listdir(PATH+'sandbox/template') == []:
				if not self.makeTemplate():
					return False
			self.destroy_box()
			
			os.mkdir(self.sandbox_path)
			
			if subprocess.call(['rsync','-a',self.sandbox_template_path+'/',self.sandbox_path]) != 0:
				return False
			
			self.state = self.STATE_READY
			self.commands = []
			self.success = False
			
			return True
		except Exception as inst:
			print(inst)
			traceback.print_exc()
			return False
	def destroy_box(self):
		if os.path.isdir(self.sandbox_path):
			shutil.rmtree(self.sandbox_path)
	
	def build_github(self,user,repo,branch):
		self.commands += [
			{
				'type':'cmd',
				'cmd':['wget','https://github.com/'+user+'/'+repo+'/archive/'+branch+'.zip']
			},
			{
				'type':'cmd',
				'cmd':['unzip','master.zip']
			},
			{
				'type':'cmd',
				'shell':True,
				'cmd':'mv '+repo+'-'+branch+'/* .'
			},
			{
				'type':'cmd',
				'cmd':['ls','-l']
			}
		]
		
	def demote(self):
		try:
			print('Running chroot ('+str(self.i)+')...')
			if self.state != self.STATE_READY:
				return
			self.state = self.STATE_RUNNING
			for t in self.mount_binds:
				subprocess.call(['mount','--bind','/dev/'+t,self.sandbox_path+'/dev/'+t])
			os.chroot(self.sandbox_path)
			
			os.setgid(GID) # set GID before UID so that we actually have permission
			os.setuid(UID)
			
			os.chdir('/build')
			self.success = True
			for c in self.commands:
				print('Executing command',c)
				if c['type'] == 'cmd':
					if subprocess.call(c['cmd'],shell=('shell' in c and c['shell'])) != 0:
						self.success = False
						break
				elif c['type'] == 'exec':
					c['fn']()
			
			print('done with this chroot!')
			self.state = self.STATE_DONE # we need to trash the chroot, it may be unsafe!
		except:
			traceback.print_exc()
			self.success = False
			self.state = self.STATE_DONE # just trash the chroot
	def run(self):
		subprocess.call([':'],shell=True,preexec_fn=self.demote) # hack so that the parent isn't effected
		for t in self.mount_binds:
			subprocess.call(['umount',self.sandbox_path+'/dev/'+t])
		

if __name__ == '__main__':
	if not os.path.isdir(PATH+'sandbox'):
		if os.path.exists(PATH+'sandbox'):
			print('ERROR: '+PATH+'sandbox exists in the filesystem')
			sys.exit(1)
		os.makedirs(PATH+'sandbox')
	try:
		UID = pwd.getpwnam(config['chroot']['user'])[2]
		GID = grp.getgrnam(config['chroot']['group'])[2]
	except:
		print('ERROR: invalid user / group in config')
		sys.exit(1)
	jail = Chroot_jail(0)
	if not jail.build_box():
		print('Something went wrong building the box....')
	jail.build_github('Sorunome','blockdude-gamebuino','master')
	
	jail.start()
	try:
		while True:
			time.sleep(30)
	except KeyboardInterrupt:
		print('joining with jail...')
		jail.join()
